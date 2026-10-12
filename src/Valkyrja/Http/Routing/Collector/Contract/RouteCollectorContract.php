<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Collector\Contract;

use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Throwable\Exception\HttpRoutingNoRouteHandlerException;

interface RouteCollectorContract
{
    /**
     * Get route attributes.
     *
     * @param class-string ...$classes The classes
     *
     * @throws HttpRoutingNoRouteHandlerException
     *
     * @return RouteContract[]
     */
    public function getRoutes(string ...$classes): array;
}
