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
use Valkyrja\Application\Provider\CliApplicationComponentProvider;
use Valkyrja\Application\Provider\CliWithHttpApplicationComponentProvider;
use Valkyrja\Http\Message\Provider\HttpMessageComponentProvider;
use Valkyrja\Http\Middleware\Provider\HttpMiddlewareComponentProvider;
use Valkyrja\Http\Routing\Provider\HttpRoutingCliComponentProvider;
use Valkyrja\Http\Routing\Provider\HttpRoutingComponentProvider;
use Valkyrja\Http\Server\Provider\HttpServerComponentProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the Component service.
 */
final class CliWithHttpApplicationComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        $providers = new CliWithHttpApplicationComponentProvider()->getComponentProviders($app);

        self::assertCount(6, $providers);
        self::assertInstanceOf(CliApplicationComponentProvider::class, $providers[0]);
        self::assertInstanceOf(HttpMessageComponentProvider::class, $providers[1]);
        self::assertInstanceOf(HttpMiddlewareComponentProvider::class, $providers[2]);
        self::assertInstanceOf(HttpRoutingComponentProvider::class, $providers[3]);
        self::assertInstanceOf(HttpRoutingCliComponentProvider::class, $providers[4]);
        self::assertInstanceOf(HttpServerComponentProvider::class, $providers[5]);
    }

    public function testGetContainerProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliWithHttpApplicationComponentProvider()->getContainerProviders($app));
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliWithHttpApplicationComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliWithHttpApplicationComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliWithHttpApplicationComponentProvider()->getHttpProviders($app));
    }

    public function testGetGrpcProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CliWithHttpApplicationComponentProvider()->getGrpcProviders($app));
    }
}
