<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cache\Provider;

use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Cache\Provider\CacheComponentProvider;
use Valkyrja\Cache\Provider\CacheServiceProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the Component service.
 */
final class ComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CacheComponentProvider()->getComponentProviders($app));
    }

    public function testGetContainerProvider(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertInstanceOf(CacheServiceProvider::class, new CacheComponentProvider()->getContainerProviders($app)[0]);
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CacheComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CacheComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CacheComponentProvider()->getHttpProviders($app));
    }

    public function testGetGrpcProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new CacheComponentProvider()->getGrpcProviders($app));
    }
}
