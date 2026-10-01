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
use Predis\ClientInterface;
use Valkyrja\Application\Entry\Redis\RedisQueue;
use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;

/**
 * A redis queue entry that polls an injected connection instead of a real one.
 */
final class RedisQueueFixture extends RedisQueue
{
    private static ClientInterface|null $injected = null;

    /**
     * Point the entry at a connection, as though connect() had established it.
     *
     * @param non-empty-string $queue   The list key jobs are popped from
     * @param int<1, max>      $timeout The blocking pop timeout, in seconds
     */
    public static function inject(ClientInterface $redis, string $queue = 'queues:default', int $timeout = 1): void
    {
        self::$injected  = $redis;
        static::$redis   = $redis;
        static::$queue   = $queue;
        static::$timeout = $timeout;
    }

    /**
     * Drop the connection and the overrides, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$injected  = null;
        static::$redis   = null;
        static::$queue   = 'queues:default';
        static::$timeout = 1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected static function getRedis(QueueRedisClientConfigContract $config): ClientInterface
    {
        return self::$injected ?? parent::getRedis($config);
    }
}
