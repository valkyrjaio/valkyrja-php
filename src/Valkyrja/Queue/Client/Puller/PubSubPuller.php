<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Puller;

use Google\ApiCore\ApiException;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\Subscription;
use Google\Rpc\Code;
use GuzzleHttp\Exception\ConnectException;
use JsonException;
use Override;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Puller\Contract\PullerContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

/**
 * Consumes from a Google Cloud Pub/Sub subscription, and settles with it.
 *
 * This is both the puller and the re-queuer, for the same reason the AMQP and
 * SQS adapters are: settling means acting on the native delivery, and only the
 * thing that received it holds the acknowledgement id. The framework never sees
 * an ack id — it hands over an outcome enum and this translates it.
 *
 * A pull holds the connection open until a message arrives or the deadline
 * passes, so it needs a deadline of its own. Without one a worker on an empty
 * subscription would block for ever and never reach the entry's loop bounds or
 * its graceful shutdown.
 */
class PubSubPuller implements PullerContract, RequeuerContract
{
    /**
     * The delivery currently in flight, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough — and
     * it is cleared on settlement so a second settle cannot double-acknowledge.
     */
    protected Message|null $current = null;

    /**
     * @param int<1, max> $timeoutMs The deadline for one pull, in milliseconds
     */
    public function __construct(
        protected Subscription $subscription,
        protected int $timeoutMs = 1000,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     *
     * Pub/Sub is a managed service, so there is no connection to open.
     */
    #[Override]
    public function connect(): void
    {
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function receive(): JobContract|null
    {
        $messages = $this->pull();

        $message = $messages[0] ?? null;

        if (! $message instanceof Message) {
            return null;
        }

        $this->current = $message;

        return $this->factory->fromJson($message->data());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        // Anything still in flight was not completed, so hand it back rather
        // than letting it wait out the whole acknowledgement deadline
        $this->releaseCurrent();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $message = $this->current;

        if (! $message instanceof Message) {
            return;
        }

        $this->current = null;

        if ($result === JobResult::RETRY) {
            $this->release($message);

            return;
        }

        // A dead letter is terminal here as well. Pub/Sub moves a message to
        // the dead-letter topic on the delivery-attempt count, so the framework
        // acknowledging it is what stops the chain.
        $this->subscription->acknowledge($message);
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
    protected function pull(): array
    {
        try {
            return $this->subscription->pull([
                'maxMessages'   => 1,
                'timeoutMillis' => $this->timeoutMs,
            ]);
        } catch (ConnectException) {
            return [];
        } catch (ApiException $exception) {
            if ($exception->getCode() !== Code::DEADLINE_EXCEEDED) {
                throw $exception;
            }

            return [];
        }
    }

    /**
     * Hand any in-flight delivery back to the subscription.
     */
    protected function releaseCurrent(): void
    {
        $message = $this->current;

        if ($message instanceof Message) {
            $this->current = null;

            $this->release($message);
        }
    }

    /**
     * Make a delivery redeliverable at once.
     *
     * A zero deadline is Pub/Sub's nack: the message becomes available again
     * and its delivery-attempt count goes up.
     */
    protected function release(Message $message): void
    {
        $this->subscription->modifyAckDeadline($message, 0);
    }
}
