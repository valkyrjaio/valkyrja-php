<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Support;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Throwable\Exception\QueueRoutingNoRouteHandlerException;

final class NoRouteHandler
{
    /**
     * Stand in for a handler the author did not supply.
     *
     * A route cannot be built without a handler, and the collector rejects a
     * route that still carries this one, so reaching it means a route was
     * built by hand with none. It throws rather than acknowledging, because
     * an acknowledgement destroys the job it was given.
     *
     * @throws QueueRoutingNoRouteHandlerException
     */
    public function __invoke(ContainerContract $container, RouteContract $route): JobResult
    {
        throw new QueueRoutingNoRouteHandlerException(
            'Route `' . $route->getName() . '` carries no handler'
        );
    }
}
