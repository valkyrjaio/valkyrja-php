<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\Amqp;

use JsonException;
use Override;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Valkyrja\Application\Entry\Abstract\PullQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Client\Data\Contract\QueueAmqpClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;

use function is_array;
use function is_int;
use function max;
use function sleep;

class AmqpQueue extends PullQueue
{
    /**
     * The header a quorum queue uses to report how many times it redelivered.
     *
     * @var non-empty-string
     */
    public const string DELIVERY_COUNT_HEADER = 'x-delivery-count';

    /** @var int<0, max> The seconds to wait for a delivery; 0 to poll without blocking */
    protected static int $timeout = 1;

    protected static AMQPChannel|null $channel = null;

    /** @var non-empty-string */
    protected static string $queue = 'queues.default';

    /**
     * The delivery currently in flight, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough, and
     * it is cleared on settlement so a second settle cannot double-ack.
     */
    protected static AMQPMessage|null $current = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        $config = $app->getContainer()->getSingleton(QueueAmqpClientConfigContract::class);

        static::$queue   = $config->amqpQueue;
        static::$channel = static::getChannel($config);

        $channel = static::getConnection();

        // Declaring is idempotent, so a consumer may start before any producer
        $channel->queue_declare(static::$queue, false, true, false, false);
        // One unacknowledged delivery at a time, matching the single in-flight slot
        $channel->basic_qos(0, 1, false);
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public static function receive(): JobContract|null
    {
        $message = static::getConnection()->basic_get(static::$queue);

        if (! $message instanceof AMQPMessage) {
            static::wait();

            return null;
        }

        static::$current = $message;

        return static::withNormalizedAttempts(new JobFactory()->fromJson($message->getBody()), $message);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        // Anything still in flight was not completed, so hand it back rather
        // than letting it wait out the broker's own timeout
        static::releaseCurrent();

        static::getConnection()->close();

        static::$channel = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $message = static::$current;

        if (! $message instanceof AMQPMessage) {
            return;
        }

        static::$current = null;

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
    protected static function withNormalizedAttempts(JobContract $job, AMQPMessage $message): JobContract
    {
        $count = static::getDeliveryCount($message);

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
    protected static function getDeliveryCount(AMQPMessage $message): int|null
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
     * Open the channel the loop consumes from.
     */
    protected static function getChannel(QueueAmqpClientConfigContract $config): AMQPChannel
    {
        return new AMQPStreamConnection(
            $config->amqpHost,
            $config->amqpPort,
            $config->amqpUser,
            $config->amqpPassword,
            $config->amqpVhost,
        )->channel();
    }

    /**
     * Get the channel that connect() opened.
     *
     * @throws QueueServerNotConnectedException
     */
    protected static function getConnection(): AMQPChannel
    {
        return static::$channel
            ?? throw new QueueServerNotConnectedException('The AMQP queue has no channel to consume from.');
    }

    /**
     * Hand any in-flight delivery back to the broker.
     */
    protected static function releaseCurrent(): void
    {
        $message = static::$current;

        if ($message instanceof AMQPMessage) {
            static::$current = null;

            $message->nack(true);
        }
    }

    /**
     * Yield for the configured timeout when nothing was waiting.
     *
     * A polling consumer must yield, or the entry's loop bounds and graceful
     * shutdown would never get a chance to run.
     */
    protected static function wait(): void
    {
        if (static::$timeout > 0) {
            static::pause(static::$timeout);
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
    protected static function pause(int $seconds): void
    {
        sleep($seconds);
    }
}
