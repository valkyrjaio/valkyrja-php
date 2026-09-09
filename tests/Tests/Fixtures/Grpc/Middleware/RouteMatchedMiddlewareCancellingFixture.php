<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Middleware;

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

/**
 * Fires the call's cancellation token mid-flight and returns the route, so the handler's post-check
 * detects a cancellation that arrived during middleware execution.
 */
final class RouteMatchedMiddlewareCancellingFixture implements RouteMatchedMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function routeMatched(ServiceCallContract $call, RouteContract $route, RouteMatchedHandlerContract $handler): RouteContract|ServiceResponseContract
    {
        $this->updateCounter();

        $cancellation = $call->getCancellation();

        if ($cancellation instanceof CancellationToken) {
            $cancellation->cancel(CancellationReason::CLIENT_CANCELLED);
        }

        return $route;
    }
}
