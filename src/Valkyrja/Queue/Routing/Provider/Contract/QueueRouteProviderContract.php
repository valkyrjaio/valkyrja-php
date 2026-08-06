<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Provider\Contract;

use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

interface QueueRouteProviderContract
{
    /**
     * Get a list of attributed job handler classes.
     *
     * @return class-string[]
     */
    public function getControllerClasses(): array;

    /**
     * Get a list of routes.
     *
     * @return RouteContract[]
     */
    public function getRoutes(): array;
}
