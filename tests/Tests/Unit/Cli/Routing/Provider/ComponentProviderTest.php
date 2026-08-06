<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cli\Routing\Provider;

use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Cli\Routing\Provider\CliRoutingCliRouteProvider;
use Valkyrja\Cli\Routing\Provider\CliRoutingComponentProvider;
use Valkyrja\Cli\Routing\Provider\CliRoutingServiceProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the Component service.
 */
final class ComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliRoutingComponentProvider()->getComponentProviders($app));
    }

    public function testGetContainerProvider(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertInstanceOf(CliRoutingServiceProvider::class, new CliRoutingComponentProvider()->getContainerProviders($app)[0]);
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        $providers = new CliRoutingComponentProvider()->getCliProviders($app);

        self::assertInstanceOf(CliRoutingCliRouteProvider::class, $providers[0]);
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliRoutingComponentProvider()->getEventProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliRoutingComponentProvider()->getHttpProviders($app));
    }

    public function testGetQueueProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliRoutingComponentProvider()->getQueueProviders($app));
    }
}
