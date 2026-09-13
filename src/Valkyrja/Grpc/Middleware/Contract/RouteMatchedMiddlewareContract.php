<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Middleware\Contract;

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

interface RouteMatchedMiddlewareContract
{
    /**
     * Middleware handler for after a route has been matched but before it has been dispatched.
     *
     * Returns the (possibly updated) route to dispatch to the handler, or a response that
     * short-circuits the pipeline.
     */
    public function routeMatched(ServiceCallContract $call, RouteContract $route, RouteMatchedHandlerContract $handler): RouteContract|ServiceResponseContract;
}
