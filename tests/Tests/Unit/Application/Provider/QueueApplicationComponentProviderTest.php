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
use Valkyrja\Application\Provider\QueueApplicationComponentProvider;
use Valkyrja\Log\Provider\LogComponentProvider;
use Valkyrja\Queue\Message\Provider\QueueMessageComponentProvider;
use Valkyrja\Queue\Middleware\Provider\QueueMiddlewareComponentProvider;
use Valkyrja\Queue\Routing\Provider\QueueRoutingComponentProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueueApplicationComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        $providers = new QueueApplicationComponentProvider()->getComponentProviders($app);

        self::assertInstanceOf(ApplicationComponentProvider::class, $providers[0]);
        self::assertInstanceOf(QueueMessageComponentProvider::class, $providers[1]);
        self::assertInstanceOf(QueueMiddlewareComponentProvider::class, $providers[2]);
        self::assertInstanceOf(QueueRoutingComponentProvider::class, $providers[3]);
        self::assertInstanceOf(LogComponentProvider::class, $providers[4]);
    }

    public function testGetContainerProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueApplicationComponentProvider()->getContainerProviders($app));
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueApplicationComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueApplicationComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueApplicationComponentProvider()->getHttpProviders($app));
    }

    public function testGetQueueProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueApplicationComponentProvider()->getQueueProviders($app));
    }
}
