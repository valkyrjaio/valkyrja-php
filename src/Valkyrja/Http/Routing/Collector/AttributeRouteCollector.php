<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Collector;

use Override;
use ReflectionException;
use ReflectionMethod;
use Valkyrja\Attribute\Collector\Collector;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Http\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Http\Routing\Attribute\DynamicRoute as DynamicAttribute;
use Valkyrja\Http\Routing\Attribute\Parameter;
use Valkyrja\Http\Routing\Attribute\Route as Attribute;
use Valkyrja\Http\Routing\Attribute\Route\Middleware;
use Valkyrja\Http\Routing\Attribute\Route\Name;
use Valkyrja\Http\Routing\Attribute\Route\Path;
use Valkyrja\Http\Routing\Attribute\Route\RequestMethod;
use Valkyrja\Http\Routing\Attribute\Route\RequestStruct;
use Valkyrja\Http\Routing\Attribute\Route\ResponseStruct;
use Valkyrja\Http\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Http\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Http\Routing\Data\Contract\ParameterContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Data\DynamicRoute;
use Valkyrja\Http\Routing\Data\Parameter as DataParameter;
use Valkyrja\Http\Routing\Data\Route;
use Valkyrja\Http\Routing\Factory\RouteFactory;
use Valkyrja\Http\Routing\Processor\Contract\ProcessorContract;
use Valkyrja\Http\Routing\Processor\Processor;
use Valkyrja\Http\Routing\Support\NoRouteHandler;
use Valkyrja\Http\Routing\Throwable\Exception\HttpRoutingNoRouteHandlerException;
use Valkyrja\Http\Struct\Request\Contract\RequestStructContract;
use Valkyrja\Http\Struct\Response\Contract\ResponseStructContract;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;
use Valkyrja\Reflection\Reflector\Reflector;

use function array_column;
use function array_merge;
use function is_a;

class AttributeRouteCollector implements RouteCollectorContract
{
    public function __construct(
        protected CollectorContract $attributes = new Collector(),
        protected ReflectorContract $reflection = new Reflector(),
        protected ProcessorContract $processor = new Processor()
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws ReflectionException
     */
    #[Override]
    public function getRoutes(string ...$classes): array
    {
        $routes = [];

        foreach ($classes as $class) {
            /** @var array<Attribute|DynamicAttribute> $attributes */
            $attributes = array_merge(
                $this->attributes->forClassMembers($class, Attribute::class),
                $this->attributes->forClassMembers($class, DynamicAttribute::class),
            );

            // Iterate through all the members' attributes
            foreach ($attributes as $attribute) {
                /** @var ReflectionMethod $reflection */
                $reflection = $attribute->getReflection();
                $method     = $reflection->getName();
                /** @var Route $route */
                $route      = RouteFactory::fromRoute($attribute);

                $route = $this->updatePath($route, $class, $method);
                $route = $this->updateName($route, $class, $method);
                $route = $this->updateHandler($route, $class, $method);
                $route = $this->updateMiddleware($route, $class, $method);
                $route = $this->updateRequestStruct($route, $class, $method);
                $route = $this->updateResponseStruct($route, $class, $method);
                $route = $this->updateRequestMethods($route, $class, $method);

                if ($route instanceof DynamicRoute) {
                    $route = $this->updateParameters($route, $class, $method);
                }

                // And set a new route with the controller defined annotation additions
                $routes[] = $this->setRouteProperties($route);
            }
        }

        return $routes;
    }

    /**
     * Set the route properties from arguments.
     */
    protected function setRouteProperties(RouteContract $route): RouteContract
    {
        return $this->processor->route($route);
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws ReflectionException
     */
    protected function updatePath(Route $route, string $class, string $method): Route
    {
        /** @var Path[] $classPaths */
        $classPaths = $this->attributes->forClass($class, Path::class);
        $routePaths = $this->attributes->forMethod($class, $method, Path::class);

        /** @var non-empty-string[] $classPath */
        $classPath = array_column($classPaths, 'value');

        if ($classPath !== []) {
            $route = $route->withPath($classPath[0] . $route->getPath());
        }

        /** @var non-empty-string[] $routePath */
        $routePath = array_column($routePaths, 'value');

        if ($routePath !== []) {
            $route = $route->withAddedPath($routePath[0]);
        }

        return $route;
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws ReflectionException
     */
    protected function updateName(Route $route, string $class, string $method): Route
    {
        /** @var Name[] $classNames */
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
     * Update the handler for a route.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws ReflectionException
     * @throws HttpRoutingNoRouteHandlerException
     */
    protected function updateHandler(Route $route, string $class, string $method): Route
    {
        /** @var RouteHandler[] $routeHandlers */
        $routeHandlers = $this->attributes->forMethod($class, $method, RouteHandler::class);
        $routeHandler  = $routeHandlers[0] ?? null;

        // The route attribute's own handler argument supplies a handler too, so
        // the route carries one already unless the author named none
        $handler = $routeHandler->handler ?? $route->getHandler();

        // A route that keeps the stand-in fails only when a request reaches it,
        // so the collector rejects it here
        if ($handler instanceof NoRouteHandler) {
            throw new HttpRoutingNoRouteHandlerException(
                'Route `' . $route->getName() . '` on ' . $class . '::' . $method
                . ' names no handler'
            );
        }

        return $route->withHandler($handler);
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws ReflectionException
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
            $route = $this->updateSendingResponseMiddleware($route, $middlewareClass);
            $route = $this->updateResponseSentMiddleware($route, $middlewareClass);
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
    protected function updateSendingResponseMiddleware(Route $route, string $middleware): Route
    {
        if (is_a($middleware, SendingResponseMiddlewareContract::class, true)) {
            $route = $route->withAddedSendingResponseMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string $middleware The middleware
     */
    protected function updateResponseSentMiddleware(Route $route, string $middleware): Route
    {
        if (is_a($middleware, ResponseSentMiddlewareContract::class, true)) {
            $route = $route->withAddedResponseSentMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws ReflectionException
     */
    protected function updateRequestStruct(Route $route, string $class, string $method): Route
    {
        $requestStruct = $this->attributes->forMethod($class, $method, RequestStruct::class);

        /** @var RequestStructContract[] $requestStructName */
        $requestStructName = array_column($requestStruct, 'struct');

        if ($requestStructName !== []) {
            $route = $route->withRequestStruct($requestStructName[0]);
        }

        return $route;
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws ReflectionException
     */
    protected function updateResponseStruct(Route $route, string $class, string $method): Route
    {
        $responseStruct = $this->attributes->forMethod($class, $method, ResponseStruct::class);

        /** @var ResponseStructContract[] $responseStructName */
        $responseStructName = array_column($responseStruct, 'struct');

        if ($responseStructName !== []) {
            $route = $route->withResponseStruct($responseStructName[0]);
        }

        return $route;
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws ReflectionException
     */
    protected function updateRequestMethods(Route $route, string $class, string $method): Route
    {
        $requestMethods = $this->attributes->forMethod($class, $method, RequestMethod::class);

        foreach ($requestMethods as $requestMethod) {
            /** @psalm-suppress MixedArgument Unsure why Psalm doesn't realize that the requestMethods property is an array of RequestMethod enums */
            $route = $route->withAddedRequestMethods(...$requestMethod->requestMethods);
        }

        return $route;
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws ReflectionException
     */
    protected function updateParameters(DynamicRoute $route, string $class, string $method): DynamicRoute
    {
        $route = $route->withParameters(
            ...$route->getParameters(),
            ...$this->attributes->forMethod($class, $method, Parameter::class),
            ...$this->attributes->forMethodParameters($class, $method, Parameter::class),
        );

        $parameterAttributes = $route->getParameters();
        $parameters          = [];

        foreach ($parameterAttributes as $parameterAttribute) {
            $parameters[] = $this->convertParameterAttributesToDataClass($parameterAttribute);
        }

        return $route->withParameters(...$parameters);
    }

    protected function convertParameterAttributesToDataClass(ParameterContract $parameter): DataParameter
    {
        return new DataParameter(
            name: $parameter->getName(),
            regex: $parameter->getRegex(),
            cast: $parameter->hasCast()
                ? $parameter->getCast()
                : null,
            isOptional: $parameter->isOptional(),
            shouldCapture: $parameter->shouldCapture(),
            default: $parameter->getDefault()
        );
    }
}
