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

use AsyncAws\Sqs\SqsClient;
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
 * Consumes from Amazon SQS, and settles with it.
 *
 * This is both the puller and the re-queuer, for the same reason the AMQP
 * adapter is: settling means acting on the native delivery, and only the thing
 * that received it holds the receipt handle. The framework never sees a receipt
 * handle — it hands over an outcome enum and this translates it.
 *
 * SQS needs no separate wait. A receive is a long poll, so an empty queue
 * already blocks for the wait time and then returns nothing.
 */
class SqsPuller implements PullerContract, RequeuerContract
{
    /**
     * The receipt handle of the delivery currently in flight, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough — and
     * it is cleared on settlement so a second settle cannot double-delete.
     */
    protected string|null $current = null;

    /**
     * @param non-empty-string $queueUrl          The queue jobs are consumed from
     * @param int<0, 20>       $waitTimeSeconds   The long-poll wait; 0 polls without blocking
     * @param int<0, max>      $visibilityTimeout The seconds a delivery stays hidden from other consumers
     */
    public function __construct(
        protected SqsClient $sqs,
        protected string $queueUrl,
        protected int $waitTimeSeconds = 1,
        protected int $visibilityTimeout = 30,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     *
     * SQS is a managed service, so there is no connection to open.
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
        $result = $this->sqs->receiveMessage([
            'QueueUrl'            => $this->queueUrl,
            'MaxNumberOfMessages' => 1,
            'WaitTimeSeconds'     => $this->waitTimeSeconds,
            'VisibilityTimeout'   => $this->visibilityTimeout,
        ]);

        $message = $result->getMessages()[0] ?? null;

        if ($message === null) {
            return null;
        }

        $body = $message->getBody();

        if ($body === null) {
            return null;
        }

        $handle = $message->getReceiptHandle();

        // Settling needs the handle, so a delivery without one can be neither
        // deleted nor made visible again. Running it would leave SQS to
        // redeliver the same message on every visibility timeout, forever
        if ($handle === null) {
            return null;
        }

        $this->current = $handle;

        return $this->factory->fromJson($body);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        // Anything still in flight was not completed, so release it rather than
        // letting it wait out the whole visibility timeout
        $this->releaseCurrent();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $handle = $this->current;

        if ($handle === null) {
            return;
        }

        $this->current = null;

        if ($result === JobResult::RETRY) {
            // Make it visible again: SQS redelivers and counts the receives
            $this->changeVisibility($handle, 0);

            return;
        }

        // A dead letter is terminal here as well. SQS moves a message to the
        // dead-letter queue through the redrive policy, on the receive count,
        // so the framework deleting it is what stops the chain.
        $this->sqs->deleteMessage([
            'QueueUrl'      => $this->queueUrl,
            'ReceiptHandle' => $handle,
        ]);
    }

    /**
     * Hand any in-flight delivery back to the queue.
     */
    protected function releaseCurrent(): void
    {
        $handle = $this->current;

        if ($handle !== null) {
            $this->current = null;

            $this->changeVisibility($handle, 0);
        }
    }

    /**
     * Set how long a delivery stays hidden from other consumers.
     *
     * @param int<0, max> $timeout The seconds to stay hidden; 0 makes it visible at once
     */
    protected function changeVisibility(string $handle, int $timeout): void
    {
        $this->sqs->changeMessageVisibility([
            'QueueUrl'          => $this->queueUrl,
            'ReceiptHandle'     => $handle,
            'VisibilityTimeout' => $timeout,
        ]);
    }
}
