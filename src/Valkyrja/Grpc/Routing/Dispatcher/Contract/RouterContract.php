<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Dispatcher\Contract;

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * Resolves an inbound call to a route via a direct service-map lookup and dispatches it.
 *
 * The component keeps the Router name for consistency with HTTP and CLI; only the resolution
 * strategy (map lookup, no pattern matching) differs.
 */
interface RouterContract
{
    /**
     * Dispatch a call.
     *
     * @param ServiceCallContract $call The call
     */
    public function dispatch(ServiceCallContract $call): ServiceResponseContract;

    /**
     * Dispatch a call to an already-resolved route.
     *
     * @param ServiceCallContract $call  The call
     * @param RouteContract       $route The route
     */
    public function dispatchRoute(ServiceCallContract $call, RouteContract $route): ServiceResponseContract;
}
