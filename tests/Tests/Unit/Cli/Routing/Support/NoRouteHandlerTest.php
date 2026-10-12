<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cli\Routing\Support;

use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Support\NoRouteHandler;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingNoRouteHandlerException;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class NoRouteHandlerTest extends TestCase
{
    public function testItThrows(): void
    {
        $route = self::createStub(RouteContract::class);

        $route->method('getName')->willReturn('test');

        // It throws rather than returning empty output, because a command
        // that writes nothing and exits successfully reads as one that worked
        $this->expectException(CliRoutingNoRouteHandlerException::class);
        $this->expectExceptionMessage('Route `test` carries no handler');

        (new NoRouteHandler())(self::createStub(ContainerContract::class), $route);
    }
}
