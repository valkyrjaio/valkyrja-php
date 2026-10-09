<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Middleware\Handler\Contract;

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * @extends HandlerContract<RouteDispatchedMiddlewareContract>
 */
interface RouteDispatchedHandlerContract extends HandlerContract
{
    /**
     * Middleware handler for after the user handler produces a response.
     */
    public function routeDispatched(ServiceCallContract $call, ServiceResponseContract $response, RouteContract $route): ServiceResponseContract;
}
