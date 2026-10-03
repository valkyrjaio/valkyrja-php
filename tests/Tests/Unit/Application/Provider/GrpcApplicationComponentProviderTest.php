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
use Valkyrja\Application\Provider\GrpcApplicationComponentProvider;
use Valkyrja\Grpc\Middleware\Provider\GrpcMiddlewareComponentProvider;
use Valkyrja\Grpc\Routing\Provider\GrpcRoutingComponentProvider;
use Valkyrja\Grpc\Server\Provider\GrpcServerComponentProvider;
use Valkyrja\Log\Provider\LogComponentProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the Component service.
 */
final class GrpcApplicationComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        $providers = new GrpcApplicationComponentProvider()->getComponentProviders($app);

        self::assertCount(5, $providers);
        self::assertInstanceOf(ApplicationComponentProvider::class, $providers[0]);
        self::assertInstanceOf(GrpcMiddlewareComponentProvider::class, $providers[1]);
        self::assertInstanceOf(GrpcRoutingComponentProvider::class, $providers[2]);
        self::assertInstanceOf(GrpcServerComponentProvider::class, $providers[3]);
        self::assertInstanceOf(LogComponentProvider::class, $providers[4]);
    }

    public function testGetContainerProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new GrpcApplicationComponentProvider()->getContainerProviders($app));
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new GrpcApplicationComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new GrpcApplicationComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new GrpcApplicationComponentProvider()->getHttpProviders($app));
    }

    public function testGetGrpcProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new GrpcApplicationComponentProvider()->getGrpcProviders($app));
    }
}
