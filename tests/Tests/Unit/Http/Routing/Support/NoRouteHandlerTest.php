<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Http\Routing\Support;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Http\Routing\Data\Route;
use Valkyrja\Http\Routing\Support\NoRouteHandler;
use Valkyrja\Http\Routing\Throwable\Exception\HttpRoutingNoRouteHandlerException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class NoRouteHandlerTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'test';

    public function testThrowsAndNamesTheRoute(): void
    {
        $route   = new Route(path: '/', name: self::NAME, handler: new NoRouteHandler());
        $handler = $route->getHandler();

        $this->expectException(HttpRoutingNoRouteHandlerException::class);
        $this->expectExceptionMessage('Route `' . self::NAME . '` carries no handler');

        $handler(self::createStub(ContainerContract::class), $route);
    }
}
