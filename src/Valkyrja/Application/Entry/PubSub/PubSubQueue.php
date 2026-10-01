<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\PubSub;

use Google\ApiCore\ApiException;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient as GooglePubSubClient;
use Google\Cloud\PubSub\Subscription;
use Google\Rpc\Code;
use GuzzleHttp\Exception\ConnectException;
use JsonException;
use Override;
use Valkyrja\Application\Entry\Abstract\PullQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Client\Data\Contract\QueuePubSubClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;

class PubSubQueue extends PullQueue
{
    /**
     * The cURL error number for a request that ran out its own deadline.
     */
    public const int CURL_OPERATION_TIMED_OUT = 28;

    /** @var int<1, max> The deadline for one pull, in milliseconds */
    protected static int $timeoutMs = 1000;

    /**
     * The subscription the worker pulls from.
     *
     * A subscription belongs to the consumer alone, so it is not part of the
     * client config. It defaults to the name of the topic, and an application
     * sets it on its own entry when the two names differ.
     *
     * @var non-empty-string|null
     */
    protected static string|null $subscriptionName = null;

    protected static Subscription|null $subscription = null;

    /**
     * The delivery currently in flight, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough, and
     * it is cleared on settlement so a second settle cannot double-acknowledge.
     */
    protected static Message|null $current = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        $config = $app->getContainer()->getSingleton(QueuePubSubClientConfigContract::class);

        static::$subscription = static::getSubscription($config);
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public static function receive(): JobContract|null
    {
        $messages = static::pull();

        $message = $messages[0] ?? null;

        if (! $message instanceof Message) {
            return null;
        }

        static::$current = $message;

        return new JobFactory()->fromJson($message->data());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        // Anything still in flight was not completed, so hand it back rather
        // than letting it wait out the whole acknowledgement deadline
        static::releaseCurrent();

        static::$subscription = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $message = static::$current;

        if (! $message instanceof Message) {
            return;
        }

        static::$current = null;

        if ($result === JobResult::RETRY) {
            static::release($message);

            return;
        }

        // A dead letter is terminal here as well. Pub/Sub moves a message to
        // the dead-letter topic on the delivery-attempt count, so the framework
        // acknowledging it is what stops the chain.
        static::getConnection()->acknowledge($message);
    }

    /**
     * Open the subscription the loop pulls from.
     */
    protected static function getSubscription(QueuePubSubClientConfigContract $config): Subscription
    {
        return new GooglePubSubClient(['projectId' => $config->pubSubProjectId])
            ->subscription(static::$subscriptionName ?? $config->pubSubTopic);
    }

    /**
     * Get the subscription that connect() opened.
     *
     * @throws QueueServerNotConnectedException
     */
    protected static function getConnection(): Subscription
    {
        return static::$subscription
            ?? throw new QueueServerNotConnectedException('The Pub/Sub queue has no subscription to pull from.');
    }

    /**
     * Ask the subscription for the next message, within the deadline.
     *
     * Both transports report a passed deadline as an error rather than as an
     * empty result, and an empty subscription is the normal case here, so a
     * deadline reads as nothing arrived. Any other failure is a real one and
     * travels on.
     *
     * `returnImmediately` would avoid the deadline, but Pub/Sub documents it as
     * able to return nothing while a message is waiting, so it cannot be used.
     *
     * @return Message[]
     */
    protected static function pull(): array
    {
        try {
            return static::getConnection()->pull([
                'maxMessages'   => 1,
                'timeoutMillis' => static::$timeoutMs,
            ]);
        } catch (ConnectException $exception) {
            if (! static::isDeadline($exception)) {
                throw $exception;
            }

            return [];
        } catch (ApiException $exception) {
            if ($exception->getCode() !== Code::DEADLINE_EXCEEDED) {
                throw $exception;
            }

            return [];
        }
    }

    /**
     * Read whether a transport failure is the pull deadline, and not an outage.
     *
     * The REST transport reports a passed deadline as a cURL timeout, and it
     * reports a refused connection, a failed name lookup, and a TLS failure the
     * same way. Only the timeout means that nothing arrived.
     *
     * Warning: the loop does not back off when a receive gives nothing, so
     * treating an outage as an empty poll would spin the worker instead of
     * surfacing the failure.
     */
    protected static function isDeadline(ConnectException $exception): bool
    {
        /** @var mixed $errno */
        $errno = $exception->getHandlerContext()['errno'] ?? null;

        return $errno === self::CURL_OPERATION_TIMED_OUT;
    }

    /**
     * Hand any in-flight delivery back to the subscription.
     */
    protected static function releaseCurrent(): void
    {
        $message = static::$current;

        if ($message instanceof Message) {
            static::$current = null;

            static::release($message);
        }
    }

    /**
     * Make a delivery redeliverable at once.
     *
     * A zero deadline is Pub/Sub's nack: the message becomes available again
     * and its delivery-attempt count goes up.
     */
    protected static function release(Message $message): void
    {
        static::getConnection()->modifyAckDeadline($message, 0);
    }
}
