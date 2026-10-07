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
     * @param non-empty-string $queue      The list key jobs are popped from
     * @param int<1, max>      $timeout    The blocking pop timeout, in seconds
     * @param non-empty-string $workerName The name of this worker's slot
     */
    public static function inject(
        ClientInterface $redis,
        string $queue = 'queues:default',
        int $timeout = 1,
        string $workerName = 'default',
    ): void {
        self::$injected   = $redis;
        self::$redis      = $redis;
        self::$queue      = $queue;
        self::$timeout    = $timeout;
        self::$workerName = $workerName;
        self::$current    = null;
    }

    /**
     * The promotion script, so a test can assert the call carries it.
     */
    public static function promoteScript(): string
    {
        return self::PROMOTE_SCRIPT;
    }

    /**
     * The reclaim script, so a test can assert the call carries it.
     */
    public static function reclaimScript(): string
    {
        return self::RECLAIM_SCRIPT;
    }

    /**
     * Pretend a receive left this envelope on the in-flight list.
     */
    public static function holding(string $envelope): void
    {
        self::$current = $envelope;
    }

    /**
     * Drop the connection and the overrides, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$injected   = null;
        self::$redis      = null;
        self::$queue      = 'queues:default';
        self::$timeout    = 1;
        self::$workerName = 'default';
        self::$current    = null;
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
