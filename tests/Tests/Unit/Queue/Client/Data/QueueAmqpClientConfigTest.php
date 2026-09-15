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

use Valkyrja\Queue\Client\Data\Contract\QueueAmqpClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueAmqpClientConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueueAmqpClientConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(QueueAmqpClientConfigContract::class, new QueueAmqpClientConfig());
    }

    public function testDefaults(): void
    {
        $config = new QueueAmqpClientConfig();

        self::assertSame('127.0.0.1', $config->amqpHost);
        self::assertSame(5672, $config->amqpPort);
        self::assertSame('guest', $config->amqpUser);
        self::assertSame('guest', $config->amqpPassword);
        self::assertSame('/', $config->amqpVhost);
        self::assertSame('queues.default', $config->amqpQueue);
        self::assertSame('', $config->amqpExchange);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueAmqpClientConfig(
            amqpHost: 'amqp.test',
            amqpPort: 5673,
            amqpUser: 'worker',
            amqpPassword: 'secret',
            amqpVhost: '/jobs',
            amqpQueue: 'queues.test',
            amqpExchange: 'jobs',
        );

        self::assertSame('amqp.test', $config->amqpHost);
        self::assertSame(5673, $config->amqpPort);
        self::assertSame('worker', $config->amqpUser);
        self::assertSame('secret', $config->amqpPassword);
        self::assertSame('/jobs', $config->amqpVhost);
        self::assertSame('queues.test', $config->amqpQueue);
        self::assertSame('jobs', $config->amqpExchange);
    }
}
