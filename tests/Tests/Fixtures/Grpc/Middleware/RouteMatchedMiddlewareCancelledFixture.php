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
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

/**
 * Returns a response already carrying a cancellation status, so the handler's post-check passes it
 * back up the stack unchanged.
 */
final class RouteMatchedMiddlewareCancelledFixture implements RouteMatchedMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function routeMatched(ServiceCallContract $call, RouteContract $route, RouteMatchedHandlerContract $handler): RouteContract|ServiceResponseContract
    {
        $this->updateCounter();

        return ServiceResponse::cancelled();
    }
}
