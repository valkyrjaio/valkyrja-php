<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Dispatcher;

use Override;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\ResponseSentHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\SendingResponseHandler;
use Valkyrja\Grpc\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Grpc\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Grpc\Routing\Collection\RouteCollection;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Grpc\Support\Cancellation;

class Router implements RouterContract
{
    public function __construct(
        protected ContainerContract $container = new Container(),
        protected RouteCollectionContract $collection = new RouteCollection(),
        protected RouteMatchedHandlerContract $routeMatchedHandler = new RouteMatchedHandler(),
        protected RouteNotMatchedHandlerContract $routeNotMatchedHandler = new RouteNotMatchedHandler(),
        protected RouteDispatchedHandlerContract $routeDispatchedHandler = new RouteDispatchedHandler(),
        protected ThrowableCaughtHandlerContract $throwableCaughtHandler = new ThrowableCaughtHandler(),
        protected SendingResponseHandlerContract $sendingResponseHandler = new SendingResponseHandler(),
        protected ResponseSentHandlerContract $responseSentHandler = new ResponseSentHandler(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dispatch(ServiceCallContract $call): ServiceResponseContract
    {
        $method = $call->getMethod();

        if (! $this->collection->has($method)) {
            return $this->routeNotMatchedHandler->routeNotMatched(
                call: $call,
                response: ServiceResponse::unimplemented()
            );
        }

        return $this->dispatchRoute(
            call: $call,
            route: $this->collection->get($method)
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dispatchRoute(ServiceCallContract $call, RouteContract $route): ServiceResponseContract
    {
        $this->routeMatched($route);

        $routedCall = $call->withRoute($route);

        $this->container->setSingleton(ServiceCallContract::class, $routedCall);

        $preCheck = Cancellation::checkAndFinalize($routedCall);

        if ($preCheck !== null) {
            return $preCheck;
        }

        $routeAfterMiddleware = $this->routeMatchedHandler->routeMatched(
            call: $routedCall,
            route: $route
        );

        // If the return value after middleware is a response return it
        if ($routeAfterMiddleware instanceof ServiceResponseContract) {
            return $routeAfterMiddleware;
        }

        // Set the route after middleware has potentially modified it in the service container
        $this->container->setSingleton(RouteContract::class, $routeAfterMiddleware);

        $handler = $routeAfterMiddleware->getHandler();

        $response = $handler($this->container, $routeAfterMiddleware);

        $postCheck = Cancellation::checkAndFinalize($routedCall, $response);

        if ($postCheck !== null) {
            return $postCheck;
        }

        return $this->routeDispatchedHandler->routeDispatched(
            call: $routedCall,
            response: $response,
            route: $routeAfterMiddleware
        );
    }

    /**
     * Do various stuff after the route has been matched.
     *
     * @param RouteContract $route The route
     */
    protected function routeMatched(RouteContract $route): void
    {
        $this->routeMatchedHandler->add(...$route->getRouteMatchedMiddleware());
        $this->routeDispatchedHandler->add(...$route->getRouteDispatchedMiddleware());
        $this->throwableCaughtHandler->add(...$route->getThrowableCaughtMiddleware());
        $this->sendingResponseHandler->add(...$route->getSendingResponseMiddleware());
        $this->responseSentHandler->add(...$route->getResponseSentMiddleware());

        // Set the found route in the service container
        $this->container->setSingleton(RouteContract::class, $route);
    }
}
