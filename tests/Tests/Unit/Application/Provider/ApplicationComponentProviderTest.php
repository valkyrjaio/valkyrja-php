<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Provider;

use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\ApplicationComponentProvider;
use Valkyrja\Container\Provider\ContainerComponentProvider;
use Valkyrja\Event\Provider\EventComponentProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the Component service.
 */
final class ApplicationComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        $providers = new ApplicationComponentProvider()->getComponentProviders($app);

        self::assertCount(2, $providers);
        self::assertInstanceOf(ContainerComponentProvider::class, $providers[0]);
        self::assertInstanceOf(EventComponentProvider::class, $providers[1]);
    }

    public function testGetContainerProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new ApplicationComponentProvider()->getContainerProviders($app));
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new ApplicationComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new ApplicationComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new ApplicationComponentProvider()->getHttpProviders($app));
    }

    public function testGetGrpcProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new ApplicationComponentProvider()->getGrpcProviders($app));
    }
}
