<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Collector\Contract;

use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

interface RouteCollectorContract
{
    /**
     * Get the routes.
     *
     * @param class-string ...$classes The classes
     *
     * @return RouteContract[]
     */
    public function getRoutes(string ...$classes): array;
}
