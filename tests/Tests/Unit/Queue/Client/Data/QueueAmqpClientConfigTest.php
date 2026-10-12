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

        self::assertSame('127.0.0.1', $config->queueAmqpClientHost);
        self::assertSame(5672, $config->queueAmqpClientPort);
        self::assertSame('guest', $config->queueAmqpClientUser);
        self::assertSame('guest', $config->queueAmqpClientPassword);
        self::assertSame('/', $config->queueAmqpClientVhost);
        self::assertSame('queues.default', $config->queueAmqpClientQueue);
        self::assertSame('', $config->queueAmqpClientExchange);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueAmqpClientConfig(
            queueAmqpClientHost: 'amqp.test',
            queueAmqpClientPort: 5673,
            queueAmqpClientUser: 'worker',
            queueAmqpClientPassword: 'secret',
            queueAmqpClientVhost: '/jobs',
            queueAmqpClientQueue: 'queues.test',
            queueAmqpClientExchange: 'jobs',
        );

        self::assertSame('amqp.test', $config->queueAmqpClientHost);
        self::assertSame(5673, $config->queueAmqpClientPort);
        self::assertSame('worker', $config->queueAmqpClientUser);
        self::assertSame('secret', $config->queueAmqpClientPassword);
        self::assertSame('/jobs', $config->queueAmqpClientVhost);
        self::assertSame('queues.test', $config->queueAmqpClientQueue);
        self::assertSame('jobs', $config->queueAmqpClientExchange);
    }
}
