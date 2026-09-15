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

        self::assertSame('127.0.0.1', $config->beanstalkdHost);
        self::assertSame(11300, $config->beanstalkdPort);
        self::assertSame('default', $config->beanstalkdTube);
        self::assertSame(60, $config->beanstalkdTimeToRelease);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueBeanstalkdClientConfig(
            beanstalkdHost: 'beanstalkd.test',
            beanstalkdPort: 11301,
            beanstalkdTube: 'jobs',
            beanstalkdTimeToRelease: 90,
        );

        self::assertSame('beanstalkd.test', $config->beanstalkdHost);
        self::assertSame(11301, $config->beanstalkdPort);
        self::assertSame('jobs', $config->beanstalkdTube);
        self::assertSame(90, $config->beanstalkdTimeToRelease);
    }
}
