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

use JsonException;
use Override;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Puller\Contract\PullerContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

use function is_array;
use function is_int;
use function max;
use function sleep;

/**
 * Consumes from an AMQP broker, and settles with it.
 *
 * This is both the puller and the re-queuer because a processor-owned adapter
 * cannot separate them: settling means acting on the *native delivery* — the
 * unacknowledged message the broker is still holding — and only the thing that
 * received it knows its delivery tag. Keeping both ends in one object is what
 * QUEUE.md means by the adapter owning both ends of a delivery.
 *
 * The framework never sees a delivery tag: it hands over an outcome enum and
 * this translates it into an ack or a nack.
 */
class AmqpPuller implements PullerContract, RequeuerContract
{
    /**
     * The header a quorum queue uses to report how many times it redelivered.
     *
     * @var non-empty-string
     */
    public const string DELIVERY_COUNT_HEADER = 'x-delivery-count';

    /**
     * The delivery currently in flight, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough — and
     * it is cleared on settlement so a second settle cannot double-ack.
     */
    protected AMQPMessage|null $current = null;

    /**
     * @param non-empty-string $queue   The queue jobs are consumed from
     * @param int<0, max>      $timeout The seconds to wait for a delivery; 0 to poll without blocking
     */
    public function __construct(
        protected AMQPChannel $channel,
        protected string $queue = 'queues.default',
        protected int $timeout = 1,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function connect(): void
    {
        // Declaring is idempotent, so a consumer may start before any producer
        $this->channel->queue_declare($this->queue, false, true, false, false);
        // One unacknowledged delivery at a time, matching the single in-flight slot
        $this->channel->basic_qos(0, 1, false);
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function receive(): JobContract|null
    {
        $message = $this->channel->basic_get($this->queue);

        if (! $message instanceof AMQPMessage) {
            $this->wait();

            return null;
        }

        $this->current = $message;

        return $this->withNormalizedAttempts($this->factory->fromJson($message->getBody()), $message);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        // Anything still in flight was not completed, so hand it back rather
        // than letting it wait out the broker's own timeout
        $this->releaseCurrent();

        $this->channel->close();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $message = $this->current;

        if (! $message instanceof AMQPMessage) {
            return;
        }

        $this->current = null;

        if ($result === JobResult::RETRY) {
            // Requeue: the broker redelivers and owns the attempt counting
            $message->nack(true);

            return;
        }

        if ($result->isDeadLettered()) {
            // Do not requeue: the broker routes it to its dead-letter exchange
            $message->nack(false);

            return;
        }

        $message->ack();
    }

    /**
     * Read the delivery count back off the broker and onto the job.
     *
     * A processor-owned adapter never rewrites the envelope, so the `attempts`
     * the producer published never advances on its own. The broker owns the
     * count, and the adapter normalizes it, which is what lets `max_attempts`
     * stop a failing chain.
     *
     * A quorum queue reports the count in `x-delivery-count`, which counts the
     * redeliveries and so is one less than the attempt number. A classic queue
     * reports only the `redelivered` flag, which says that this delivery is not
     * the first without saying which one it is.
     *
     * Warning: a classic queue therefore cannot count past the second attempt.
     * Give the queue a dead-letter policy, or declare it as a quorum queue, when
     * the ceiling has to hold.
     */
    protected function withNormalizedAttempts(JobContract $job, AMQPMessage $message): JobContract
    {
        $count = $this->getDeliveryCount($message);

        if ($count === null) {
            return $message->isRedelivered()
                ? $job->withAttempts(max($job->getAttempts(), 2))
                : $job;
        }

        return $job->withAttempts($count + 1);
    }

    /**
     * Read the broker's redelivery count, when the broker keeps one.
     *
     * @return int<0, max>|null
     */
    protected function getDeliveryCount(AMQPMessage $message): int|null
    {
        if (! $message->has('application_headers')) {
            return null;
        }

        /** @var mixed $headers */
        $headers = $message->get('application_headers');

        /** @var mixed $native */
        $native = $headers instanceof AMQPTable
            ? $headers->getNativeData()
            : $headers;

        if (! is_array($native)) {
            return null;
        }

        /** @var mixed $count */
        $count = $native[self::DELIVERY_COUNT_HEADER] ?? null;

        return is_int($count) && $count >= 0
            ? $count
            : null;
    }

    /**
     * Hand any in-flight delivery back to the broker.
     */
    protected function releaseCurrent(): void
    {
        $message = $this->current;

        if ($message instanceof AMQPMessage) {
            $this->current = null;

            $message->nack(true);
        }
    }

    /**
     * Yield for the configured timeout when nothing was waiting.
     *
     * A polling consumer must yield, or the entry's loop bounds and graceful
     * shutdown would never get a chance to run.
     */
    protected function wait(): void
    {
        if ($this->timeout > 0) {
            $this->pause($this->timeout);
        }
    }

    /**
     * Yield the process for the given seconds.
     *
     * An irreducible wall-clock call, isolated behind an overridable seam so a
     * test can drive the surrounding branch without waiting it out.
     *
     * @param int<1, max> $seconds The seconds to pause
     *
     * @codeCoverageIgnore
     */
    protected function pause(int $seconds): void
    {
        sleep($seconds);
    }
}
