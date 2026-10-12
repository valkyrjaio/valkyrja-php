<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Data;

use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueRedisClientConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueueRedisClientConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(QueueRedisClientConfigContract::class, new QueueRedisClientConfig());
    }

    public function testDefaults(): void
    {
        $config = new QueueRedisClientConfig();

        self::assertSame('127.0.0.1', $config->queueRedisClientHost);
        self::assertSame(6379, $config->queueRedisClientPort);
        self::assertSame('', $config->queueRedisClientPrefix);
        self::assertSame('queues:default', $config->queueRedisClientQueue);
        self::assertSame('default', $config->queueRedisClientWorkerName);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueRedisClientConfig(
            queueRedisClientHost: 'redis.test',
            queueRedisClientPort: 6380,
            queueRedisClientPrefix: 'app:',
            queueRedisClientQueue: 'queues:test',
            queueRedisClientWorkerName: 'worker.3',
        );

        self::assertSame('redis.test', $config->queueRedisClientHost);
        self::assertSame(6380, $config->queueRedisClientPort);
        self::assertSame('app:', $config->queueRedisClientPrefix);
        self::assertSame('queues:test', $config->queueRedisClientQueue);
        self::assertSame('worker.3', $config->queueRedisClientWorkerName);
    }
}
