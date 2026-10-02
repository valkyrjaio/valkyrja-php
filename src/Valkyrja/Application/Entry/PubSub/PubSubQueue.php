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
use Google\Cloud\PubSub\PubSubClient;
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
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;

use function ceil;
use function is_int;
use function max;
use function min;

class PubSubQueue extends PullQueue
{
    /**
     * The cURL error number for a request that ran out its own deadline.
     */
    public const int CURL_OPERATION_TIMED_OUT = 28;

    /** The longest hold Pub/Sub accepts on an acknowledgement deadline, in seconds. */
    public const int MAX_ACK_DEADLINE = 600;

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

        $job = static::decode($message);

        if ($job === null) {
            return null;
        }

        static::$current = $message;

        return static::withNormalizedAttempts($job, $message);
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
            // The deadline is the one hold Pub/Sub offers, so a retrying job
            // waits for its ramp rather than burning every attempt at once
            static::release($message, static::getAckDeadline($job));

            return;
        }

        // A dead letter is terminal here as well. Pub/Sub moves a message to
        // the dead-letter topic on the delivery-attempt count, so the framework
        // acknowledging it is what stops the chain.
        static::getConnection()->acknowledge($message);
    }

    /**
     * Read the delivery attempt back off the subscription and onto the job.
     *
     * A processor-owned adapter never rewrites the envelope, so the `attempts`
     * the producer published never advances on its own. Pub/Sub counts the
     * delivery, and the adapter normalizes that count, which is what lets
     * `max_attempts` stop a failing chain.
     *
     * Warning: Pub/Sub reports the count only on a subscription that carries a
     * dead-letter policy. Without one the count is absent, the job keeps the
     * `attempts` the producer published, and the ceiling never ends the chain.
     * Give the subscription a dead-letter policy when the ceiling has to hold.
     */
    protected static function withNormalizedAttempts(JobContract $job, Message $message): JobContract
    {
        $attempt = $message->deliveryAttempt();

        if (! is_int($attempt) || $attempt < 1) {
            return $job;
        }

        return $job->withAttempts($attempt);
    }

    /**
     * Read the envelope, and acknowledge a body the factory cannot read.
     *
     * An unreadable body never reaches the handler, so nothing settles it. The
     * subscription would redeliver the same message on every acknowledgement
     * deadline, and nothing ends that. An acknowledgement retires it, the same
     * answer a dead-lettered job takes on this processor.
     */
    protected static function decode(Message $message): JobContract|null
    {
        try {
            return new JobFactory()->fromJson($message->data());
        } catch (JsonException|QueueMessageInvalidEnvelopeException) {
            static::getConnection()->acknowledge($message);

            return null;
        }
    }

    /**
     * Open the subscription the loop pulls from.
     *
     * @codeCoverageIgnore A real Pub/Sub subscription is unavailable in a test.
     */
    protected static function getSubscription(QueuePubSubClientConfigContract $config): Subscription
    {
        return new PubSubClient(['projectId' => $config->pubSubProjectId])
            ->subscription(static::getSubscriptionName($config));
    }

    /**
     * Read the name of the subscription the worker pulls from.
     *
     * @return non-empty-string
     */
    protected static function getSubscriptionName(QueuePubSubClientConfigContract $config): string
    {
        return static::$subscriptionName ?? $config->pubSubTopic;
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
    protected static function release(Message $message, int $seconds = 0): void
    {
        static::getConnection()->modifyAckDeadline($message, $seconds);
    }

    /**
     * The acknowledgement deadline that holds the next attempt, in whole seconds.
     *
     * Pub/Sub takes whole seconds, and the envelope holds milliseconds, so a
     * sub-second hold rounds up rather than down to no hold at all.
     *
     * @return int<0, 600>
     */
    protected static function getAckDeadline(JobContract $job): int
    {
        $milliseconds = $job->getRetryDelayForAttemptMs();

        if ($milliseconds === 0) {
            return 0;
        }

        return max(1, min((int) ceil($milliseconds / 1000), self::MAX_ACK_DEADLINE));
    }
}
