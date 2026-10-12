<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Collector\Contract;

use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingNoRouteHandlerException;

interface RouteCollectorContract
{
    /**
     * Get the commands.
     *
     * @param class-string ...$classes The classes
     *
     * @throws CliRoutingNoRouteHandlerException
     *
     * @return RouteContract[]
     */
    public function getRoutes(string ...$classes): array;
}
