<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Collector;

use Override;
use ReflectionMethod;
use Valkyrja\Attribute\Collector\Collector;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Routing\Attribute\Route as Attribute;
use Valkyrja\Grpc\Routing\Attribute\Route\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingNoRouteHandlerException;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;
use Valkyrja\Reflection\Reflector\Reflector;

use function array_column;
use function is_a;

class AttributeRouteCollector implements RouteCollectorContract
{
    public function __construct(
        protected CollectorContract $attributes = new Collector(),
        protected ReflectorContract $reflection = new Reflector(),
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws GrpcRoutingNoRouteHandlerException
     */
    #[Override]
    public function getRoutes(string ...$classes): array
    {
        $routes = [];

        foreach ($classes as $class) {
            /** @var Service[] $services */
            $services = $this->attributes->forClass($class, Service::class);
            $service  = $services[0] ?? null;

            if ($service === null) {
                continue;
            }

            /** @var Attribute[] $attributes */
            $attributes = $this->attributes->forClassMembers($class, Attribute::class);

            foreach ($attributes as $attribute) {
                /** @var ReflectionMethod $reflection */
                $reflection = $attribute->getReflection();
                /** @var non-empty-string $method */
                $method = $reflection->getName();

                $route = $this->convertAttributeToData($service, $attribute, $class, $method);

                $routes[] = $this->updateMiddleware($route, $class, $method);
            }
        }

        return $routes;
    }

    /**
     * Build a route from a service and route attribute pair.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws GrpcRoutingNoRouteHandlerException
     */
    protected function convertAttributeToData(Service $service, Attribute $attribute, string $class, string $method): RouteContract
    {
        $fullMethod = '/' . $service->service . '/' . $attribute->name;

        return new Route(
            method: $fullMethod,
            handler: $this->getHandler($attribute, $class, $method),
            requestType: $attribute->requestType,
            responseType: $attribute->responseType,
            clientStreaming: $attribute->clientStreaming,
            serverStreaming: $attribute->serverStreaming,
            routeMatchedMiddleware: $attribute->routeMatchedMiddleware,
            routeDispatchedMiddleware: $attribute->routeDispatchedMiddleware,
            throwableCaughtMiddleware: $attribute->throwableCaughtMiddleware,
            sendingResponseMiddleware: $attribute->sendingResponseMiddleware,
            responseSentMiddleware: $attribute->responseSentMiddleware,
        );
    }

    /**
     * Resolve the handler the route attribute or a `#[RouteHandler]` names.
     *
     * The handler is resolved before the route is built, rather than replaced on a built route as
     * Cli, Http and Queue do. A gRPC route validates its own method at construction, and the
     * attribute declares only the remote method's name, so the collector is what joins the service
     * name to it and builds the route. A route that names no handler cannot be built at all,
     * because the route's handler is not nullable. The collector rejects it first, so the failure
     * names the route, the class and the method rather than coming from a constructor.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws GrpcRoutingNoRouteHandlerException
     *
     * @return callable(ContainerContract, RouteContract):ServiceResponseContract
     */
    protected function getHandler(Attribute $attribute, string $class, string $method): callable
    {
        /** @var RouteHandler[] $routeHandlers */
        $routeHandlers = $this->attributes->forMethod($class, $method, RouteHandler::class);
        $routeHandler  = $routeHandlers[0] ?? null;

        $handler = $routeHandler->handler ?? $attribute->handler;

        if ($handler === null) {
            throw new GrpcRoutingNoRouteHandlerException(
                'Route `' . $attribute->name . '` on ' . $class . '::' . $method . ' names no handler'
            );
        }

        return $handler;
    }

    /**
     * Dispatch each attributed middleware to every stage whose contract it implements.
     *
     * The checks are independent — never an if/else cascade — so a middleware serving several
     * stages lands in all of their buckets, and middleware is appended, never deduplicated.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     */
    protected function updateMiddleware(RouteContract $route, string $class, string $method): RouteContract
    {
        $middleware = $this->attributes->forMethod($class, $method, Middleware::class);

        /** @var class-string[] $middlewareClassNames */
        $middlewareClassNames = array_column($middleware, 'name');

        foreach ($middlewareClassNames as $middlewareClass) {
            $route = $this->updateRouteMatchedMiddleware($route, $middlewareClass);
            $route = $this->updateRouteDispatchedMiddleware($route, $middlewareClass);
            $route = $this->updateThrowableCaughtMiddleware($route, $middlewareClass);
            $route = $this->updateSendingResponseMiddleware($route, $middlewareClass);
            $route = $this->updateResponseSentMiddleware($route, $middlewareClass);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateRouteMatchedMiddleware(RouteContract $route, string $middleware): RouteContract
    {
        if (is_a($middleware, RouteMatchedMiddlewareContract::class, true)) {
            $route = $route->withAddedRouteMatchedMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateRouteDispatchedMiddleware(RouteContract $route, string $middleware): RouteContract
    {
        if (is_a($middleware, RouteDispatchedMiddlewareContract::class, true)) {
            $route = $route->withAddedRouteDispatchedMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateThrowableCaughtMiddleware(RouteContract $route, string $middleware): RouteContract
    {
        if (is_a($middleware, ThrowableCaughtMiddlewareContract::class, true)) {
            $route = $route->withAddedThrowableCaughtMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateSendingResponseMiddleware(RouteContract $route, string $middleware): RouteContract
    {
        if (is_a($middleware, SendingResponseMiddlewareContract::class, true)) {
            $route = $route->withAddedSendingResponseMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateResponseSentMiddleware(RouteContract $route, string $middleware): RouteContract
    {
        if (is_a($middleware, ResponseSentMiddlewareContract::class, true)) {
            $route = $route->withAddedResponseSentMiddleware($middleware);
        }

        return $route;
    }
}
