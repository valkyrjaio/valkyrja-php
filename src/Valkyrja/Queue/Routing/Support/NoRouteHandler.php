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
     * Throw instead of acknowledging, because a route built without a handler must not destroy the job.
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
