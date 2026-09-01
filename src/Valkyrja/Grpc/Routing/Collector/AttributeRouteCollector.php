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
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Method\MethodHandler;
use Valkyrja\Grpc\Routing\Attribute\Method\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingInvalidHandlerException;
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

            /** @var Method[] $methodAttributes */
            $methodAttributes = $this->attributes->forClassMembers($class, Method::class);

            foreach ($methodAttributes as $methodAttribute) {
                /** @var ReflectionMethod $reflection */
                $reflection = $methodAttribute->getReflection();
                /** @var non-empty-string $method */
                $method = $reflection->getName();

                $route = $this->convertAttributeToData($service, $methodAttribute, $class, $method);
                $route = $this->updateHandler($route, $class, $method);

                $routes[] = $this->updateMiddleware($route, $class, $method);
            }
        }

        return $routes;
    }

    /**
     * Build a route from a service and method attribute pair.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     */
    protected function convertAttributeToData(Service $service, Method $attribute, string $class, string $method): RouteContract
    {
        $fullMethod = '/' . $service->service . '/' . $attribute->name;

        return new Route(
            method: $fullMethod,
            handler: $this->handlerFor($class, $method),
            requestType: $attribute->requestType,
            responseType: $attribute->responseType,
            clientStreaming: $attribute->clientStreaming,
            serverStreaming: $attribute->serverStreaming,
        );
    }

    /**
     * Wire an attributed method as the route's handler.
     *
     * The method is called directly rather than through a reflection wrapper, so a throwable it
     * raises — a cancellation, say — propagates as itself instead of behind a type the status
     * mapping cannot see. Its return value is checked here because the signature is a runtime
     * contract discovered by the scan, not one the compiler can hold the controller to.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @return callable(ContainerContract, RouteContract):ServiceResponseContract
     */
    protected function handlerFor(string $class, string $method): callable
    {
        return static function (ContainerContract $container, RouteContract $route) use ($class, $method): ServiceResponseContract {
            /** @psalm-suppress MixedMethodCall The scan resolved the class and method by reflection */
            $response = $class::$method($container, $route);

            if (! $response instanceof ServiceResponseContract) {
                throw new GrpcRoutingInvalidHandlerException("The gRPC handler `$class::$method()` must return a service response for the route `{$route->getMethod()}`");
            }

            return $response;
        };
    }

    /**
     * Apply an explicit handler override, when one is attributed.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     */
    protected function updateHandler(RouteContract $route, string $class, string $method): RouteContract
    {
        /** @var MethodHandler[] $methodHandlers */
        $methodHandlers = $this->attributes->forMethod($class, $method, MethodHandler::class);
        $methodHandler  = $methodHandlers[0] ?? null;

        $handler = $methodHandler->handler ?? null;

        if ($handler === null) {
            return $route;
        }

        return $route->withHandler($handler);
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
