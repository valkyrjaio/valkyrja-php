<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Middleware\Provider;

use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Middleware\Provider\QueueMiddlewareComponentProvider;
use Valkyrja\Queue\Middleware\Provider\QueueMiddlewareServiceProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMiddlewareComponentProvider()->getComponentProviders($app));
    }

    public function testGetContainerProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertInstanceOf(QueueMiddlewareServiceProvider::class, new QueueMiddlewareComponentProvider()->getContainerProviders($app)[0]);
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMiddlewareComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMiddlewareComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMiddlewareComponentProvider()->getHttpProviders($app));
    }

    public function testGetQueueProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMiddlewareComponentProvider()->getQueueProviders($app));
    }
}
