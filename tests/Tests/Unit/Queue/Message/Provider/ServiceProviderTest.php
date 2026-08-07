<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Provider;

use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Provider\QueueMessageServiceProvider;

final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = QueueMessageServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(JobFactoryContract::class, new QueueMessageServiceProvider()->publishers());
    }

    public function testPublishJobFactory(): void
    {
        $callback = new QueueMessageServiceProvider()->publishers()[JobFactoryContract::class];
        $callback($this->container);

        self::assertInstanceOf(JobFactory::class, $this->container->getSingleton(JobFactoryContract::class));
    }
}
