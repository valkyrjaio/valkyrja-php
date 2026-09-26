<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Collector;

use Override;
use ReflectionMethod;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Routing\Attribute\Route as Attribute;
use Valkyrja\Queue\Routing\Attribute\Route\Middleware;
use Valkyrja\Queue\Routing\Attribute\Route\Name;
use Valkyrja\Queue\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Queue\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\Route;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;

use function array_column;
use function is_a;

class AttributeRouteCollector implements RouteCollectorContract
{
    public function __construct(
        protected CollectorContract $attributes,
        protected ReflectorContract $reflection,
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
            /** @var Attribute[] $attributes */
            $attributes = $this->attributes->forClassAndMembers($class, Attribute::class);

            foreach ($attributes as $attribute) {
                /** @var ReflectionMethod $reflection */
                $reflection = $attribute->getReflection();
                $method     = $reflection->getName();
                $route      = $this->convertAttributeToData($attribute);

                $route = $this->updateHandler($route, $class, $method);
                $route = $this->updateName($route, $class, $method);

                $routes[] = $this->updateMiddleware($route, $class, $method);
            }
        }

        return $routes;
    }

    /**
     * Update the handler for a route.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     */
    protected function updateHandler(Route $route, string $class, string $method): Route
    {
        /** @var RouteHandler[] $routeHandlers */
        $routeHandlers = $this->attributes->forMethod($class, $method, RouteHandler::class);
        $routeHandler  = $routeHandlers[0] ?? null;

        $handler = $routeHandler->handler ?? null;

        if ($handler === null) {
            return $route;
        }

        return $route->withHandler($handler);
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     */
    protected function updateName(Route $route, string $class, string $method): Route
    {
        $classNames = $this->attributes->forClass($class, Name::class);
        $routeNames = $this->attributes->forMethod($class, $method, Name::class);

        /** @var non-empty-string[] $className */
        $className = array_column($classNames, 'value');

        if ($className !== []) {
            $route = $route->withName($className[0] . '.' . $route->getName());
        }

        /** @var non-empty-string[] $routeName */
        $routeName = array_column($routeNames, 'value');

        if ($routeName !== []) {
            $route = $route->withName($route->getName() . '.' . $routeName[0]);
        }

        return $route;
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     */
    protected function updateMiddleware(Route $route, string $class, string $method): Route
    {
        $middleware = $this->attributes->forMethod($class, $method, Middleware::class);

        /** @var class-string[] $middlewareClassNames */
        $middlewareClassNames = array_column($middleware, 'name');

        foreach ($middlewareClassNames as $middlewareClass) {
            $route = $this->updateRouteMatchedMiddleware($route, $middlewareClass);
            $route = $this->updateRouteDispatchedMiddleware($route, $middlewareClass);
            $route = $this->updateThrowableCaughtMiddleware($route, $middlewareClass);
            $route = $this->updateSettlingResultMiddleware($route, $middlewareClass);
            $route = $this->updateResultSettledMiddleware($route, $middlewareClass);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateRouteMatchedMiddleware(Route $route, string $middleware): Route
    {
        if (is_a($middleware, RouteMatchedMiddlewareContract::class, true)) {
            $route = $route->withAddedRouteMatchedMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateRouteDispatchedMiddleware(Route $route, string $middleware): Route
    {
        if (is_a($middleware, RouteDispatchedMiddlewareContract::class, true)) {
            $route = $route->withAddedRouteDispatchedMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateThrowableCaughtMiddleware(Route $route, string $middleware): Route
    {
        if (is_a($middleware, ThrowableCaughtMiddlewareContract::class, true)) {
            $route = $route->withAddedThrowableCaughtMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateSettlingResultMiddleware(Route $route, string $middleware): Route
    {
        if (is_a($middleware, SettlingResultMiddlewareContract::class, true)) {
            $route = $route->withAddedSettlingResultMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateResultSettledMiddleware(Route $route, string $middleware): Route
    {
        if (is_a($middleware, ResultSettledMiddlewareContract::class, true)) {
            $route = $route->withAddedResultSettledMiddleware($middleware);
        }

        return $route;
    }

    /**
     * Convert the attribute into a plain route, dropping the reflection it carries.
     */
    protected function convertAttributeToData(RouteContract $route): Route
    {
        return new Route(
            name: $route->getName(),
            description: $route->getDescription(),
            handler: $route->getHandler(),
            routeMatchedMiddleware: $route->getRouteMatchedMiddleware(),
            routeDispatchedMiddleware: $route->getRouteDispatchedMiddleware(),
            throwableCaughtMiddleware: $route->getThrowableCaughtMiddleware(),
            settlingResultMiddleware: $route->getSettlingResultMiddleware(),
            resultSettledMiddleware: $route->getResultSettledMiddleware(),
        );
    }
}
