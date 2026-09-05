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

use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Message\Provider\QueueMessageComponentProvider;
use Valkyrja\Queue\Message\Provider\QueueMessageServiceProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ComponentProviderTest extends TestCase
{
    public function testGetComponentProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMessageComponentProvider()->getComponentProviders($app));
    }

    public function testGetContainerProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertInstanceOf(QueueMessageServiceProvider::class, new QueueMessageComponentProvider()->getContainerProviders($app)[0]);
    }

    public function testGetEventProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMessageComponentProvider()->getEventProviders($app));
    }

    public function testGetCliProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMessageComponentProvider()->getCliProviders($app));
    }

    public function testGetHttpProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMessageComponentProvider()->getHttpProviders($app));
    }

    public function testGetQueueProviders(): void
    {
        $app = self::createStub(ApplicationContract::class);

        self::assertEmpty(new QueueMessageComponentProvider()->getQueueProviders($app));
    }
}
