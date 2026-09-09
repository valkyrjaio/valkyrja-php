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
use Valkyrja\Application\Provider\HttpApplicationComponentProvider;
use Valkyrja\Http\Message\Provider\HttpMessageComponentProvider;
use Valkyrja\Http\Middleware\Provider\HttpMiddlewareComponentProvider;
use Valkyrja\Http\Routing\Provider\HttpRoutingCliComponentProvider;
use Valkyrja\Http\Routing\Provider\HttpRoutingComponentProvider;
use Valkyrja\Http\Server\Provider\HttpServerComponentProvider;
use Valkyrja\Log\Provider\LogComponentProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\View\Provider\ViewComponentProvider;

/**
 * Test the Component service.
 */
final class HttpApplicationComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        $providers = new HttpApplicationComponentProvider()->getComponentProviders($app);

        self::assertCount(8, $providers);
        self::assertInstanceOf(ApplicationComponentProvider::class, $providers[0]);
        self::assertInstanceOf(HttpMessageComponentProvider::class, $providers[1]);
        self::assertInstanceOf(HttpMiddlewareComponentProvider::class, $providers[2]);
        self::assertInstanceOf(HttpRoutingComponentProvider::class, $providers[3]);
        self::assertInstanceOf(HttpRoutingCliComponentProvider::class, $providers[4]);
        self::assertInstanceOf(HttpServerComponentProvider::class, $providers[5]);
        self::assertInstanceOf(LogComponentProvider::class, $providers[6]);
        self::assertInstanceOf(ViewComponentProvider::class, $providers[7]);
    }

    public function testGetContainerProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new HttpApplicationComponentProvider()->getContainerProviders($app));
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new HttpApplicationComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new HttpApplicationComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new HttpApplicationComponentProvider()->getHttpProviders($app));
    }

    public function testGetGrpcProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new HttpApplicationComponentProvider()->getGrpcProviders($app));
    }
}
