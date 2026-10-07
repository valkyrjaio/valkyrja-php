<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Provider;

use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Client\Provider\QueueClientComponentProvider;
use Valkyrja\Queue\Client\Provider\QueueClientServiceProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueClientComponentProvider()->getComponentProviders($app));
    }

    public function testGetContainerProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertInstanceOf(QueueClientServiceProvider::class, new QueueClientComponentProvider()->getContainerProviders($app)[0]);
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueClientComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueClientComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueClientComponentProvider()->getHttpProviders($app));
    }

    public function testGetQueueProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueClientComponentProvider()->getQueueProviders($app));
    }
}
