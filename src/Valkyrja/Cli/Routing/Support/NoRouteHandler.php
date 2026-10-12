<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Support;

use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingNoRouteHandlerException;
use Valkyrja\Container\Manager\Contract\ContainerContract;

final class NoRouteHandler
{
    /**
     * Throw for a route that names no handler.
     *
     * @throws CliRoutingNoRouteHandlerException
     */
    public function __invoke(ContainerContract $container, RouteContract $route): OutputContract
    {
        throw new CliRoutingNoRouteHandlerException(
            'Route `' . $route->getName() . '` carries no handler'
        );
    }
}
