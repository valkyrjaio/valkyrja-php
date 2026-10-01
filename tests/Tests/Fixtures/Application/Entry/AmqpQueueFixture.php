<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Entry;

use Override;
use PhpAmqpLib\Channel\AMQPChannel;
use Valkyrja\Application\Entry\Amqp\AmqpQueue;
use Valkyrja\Queue\Client\Data\Contract\QueueAmqpClientConfigContract;

/**
 * An AMQP queue entry that consumes an injected channel and never sleeps.
 *
 * The poll yield is an irreducible wall-clock call, so it is driven through an
 * overridable seam rather than making the suite wait it out.
 */
final class AmqpQueueFixture extends AmqpQueue
{
    public static int $waits = 0;

    private static AMQPChannel|null $injected = null;

    /**
     * Point the entry at a channel, as though connect() had opened it.
     *
     * @param non-empty-string $queue   The queue jobs are consumed from
     * @param int<0, max>      $timeout The seconds to wait for a delivery
     */
    public static function inject(AMQPChannel $channel, string $queue = 'queues.default', int $timeout = 0): void
    {
        self::$injected  = $channel;
        static::$channel = $channel;
        static::$queue   = $queue;
        static::$timeout = $timeout;
        self::$waits     = 0;
    }

    /**
     * Drop the channel and the overrides, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$injected  = null;
        static::$channel = null;
        static::$current = null;
        static::$queue   = 'queues.default';
        static::$timeout = 1;
        self::$waits     = 0;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected static function getChannel(QueueAmqpClientConfigContract $config): AMQPChannel
    {
        return self::$injected ?? parent::getChannel($config);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected static function pause(int $seconds): void
    {
        self::$waits += $seconds;
    }
}
