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

use Throwable;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

/**
 * A middleware serving every per-route stage at once, so a test can prove the collector's
 * classification cascade lands one class in all of its buckets.
 */
final class AllMiddlewareFixture implements RouteMatchedMiddlewareContract, RouteDispatchedMiddlewareContract, ThrowableCaughtMiddlewareContract, SendingResponseMiddlewareContract, ResponseSentMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function routeMatched(ServiceCallContract $call, RouteContract $route, RouteMatchedHandlerContract $handler): RouteContract|ServiceResponseContract
    {
        $this->updateCounter();

        return $handler->routeMatched($call, $route);
    }

    public function routeDispatched(ServiceCallContract $call, ServiceResponseContract $response, RouteContract $route, RouteDispatchedHandlerContract $handler): ServiceResponseContract
    {
        $this->updateCounter();

        return $handler->routeDispatched($call, $response, $route);
    }

    public function throwableCaught(ServiceCallContract $call, ServiceResponseContract $response, Throwable $throwable, ThrowableCaughtHandlerContract $handler): ServiceResponseContract
    {
        $this->updateCounter();

        return $handler->throwableCaught($call, $response, $throwable);
    }

    public function sendingResponse(ServiceCallContract $call, ServiceResponseContract $response, SendingResponseHandlerContract $handler): ServiceResponseContract
    {
        $this->updateCounter();

        return $handler->sendingResponse($call, $response);
    }

    public function responseSent(ServiceCallContract $call, ServiceResponseContract $response, ResponseSentHandlerContract $handler): void
    {
        $this->updateCounter();

        $handler->responseSent($call, $response);
    }
}
