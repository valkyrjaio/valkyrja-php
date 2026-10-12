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

use Valkyrja\Queue\Client\Data\Contract\QueueBeanstalkdClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueBeanstalkdClientConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueueBeanstalkdClientConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(QueueBeanstalkdClientConfigContract::class, new QueueBeanstalkdClientConfig());
    }

    public function testDefaults(): void
    {
        $config = new QueueBeanstalkdClientConfig();

        self::assertSame('127.0.0.1', $config->queueBeanstalkdClientHost);
        self::assertSame(11300, $config->queueBeanstalkdClientPort);
        self::assertSame('default', $config->queueBeanstalkdClientTube);
        self::assertSame(60, $config->queueBeanstalkdClientTimeToRelease);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueBeanstalkdClientConfig(
            queueBeanstalkdClientHost: 'beanstalkd.test',
            queueBeanstalkdClientPort: 11301,
            queueBeanstalkdClientTube: 'jobs',
            queueBeanstalkdClientTimeToRelease: 90,
        );

        self::assertSame('beanstalkd.test', $config->queueBeanstalkdClientHost);
        self::assertSame(11301, $config->queueBeanstalkdClientPort);
        self::assertSame('jobs', $config->queueBeanstalkdClientTube);
        self::assertSame(90, $config->queueBeanstalkdClientTimeToRelease);
    }
}
