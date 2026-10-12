<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Support;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Throwable\Exception\HttpRoutingNoRouteHandlerException;

final class NoRouteHandler
{
    /**
     * Refuse the request a route with no handler received.
     *
     * @throws HttpRoutingNoRouteHandlerException
     */
    public function __invoke(ContainerContract $container, RouteContract $route): ResponseContract
    {
        throw new HttpRoutingNoRouteHandlerException(
            'Route `' . $route->getName() . '` carries no handler'
        );
    }
}
