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

        self::assertSame('127.0.0.1', $config->redisHost);
        self::assertSame(6379, $config->redisPort);
        self::assertSame('queues:default', $config->redisQueue);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueRedisClientConfig(
            redisHost: 'redis.test',
            redisPort: 6380,
            redisQueue: 'queues:test',
        );

        self::assertSame('redis.test', $config->redisHost);
        self::assertSame(6380, $config->redisPort);
        self::assertSame('queues:test', $config->redisQueue);
    }
}
