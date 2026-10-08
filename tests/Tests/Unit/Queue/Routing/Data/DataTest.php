<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Routing\Data;

use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\QueueRoutingData;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class DataTest extends TestCase
{
    public function testDefault(): void
    {
        $data = new QueueRoutingData();

        self::assertEmpty($data->routes);
    }

    public function testWithRoutes(): void
    {
        $route  = self::createStub(RouteContract::class);
        $routes = [
            'route1' => static fn (): RouteContract => $route,
            'route2' => static fn (): RouteContract => $route,
        ];

        $data = new QueueRoutingData($routes);

        self::assertSame($routes, $data->routes);
        self::assertSame($route, ($data->routes['route1'])());
    }
}
