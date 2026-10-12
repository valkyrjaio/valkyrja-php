<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Collector;

use Override;
use ReflectionException;
use ReflectionMethod;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Cli\Middleware\Contract\ProcessExitingMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Cli\Routing\Attribute\ArgumentParameter as ArgumentAttribute;
use Valkyrja\Cli\Routing\Attribute\OptionParameter as OptionAttribute;
use Valkyrja\Cli\Routing\Attribute\Route as Attribute;
use Valkyrja\Cli\Routing\Attribute\Route\Middleware;
use Valkyrja\Cli\Routing\Attribute\Route\Name;
use Valkyrja\Cli\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Cli\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Cli\Routing\Data\ArgumentParameter;
use Valkyrja\Cli\Routing\Data\Contract\ArgumentParameterContract;
use Valkyrja\Cli\Routing\Data\Contract\OptionParameterContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Data\OptionParameter;
use Valkyrja\Cli\Routing\Data\Route;
use Valkyrja\Cli\Routing\Support\NoRouteHandler;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingNoRouteHandlerException;
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
     *
     * @throws ReflectionException
     */
    #[Override]
    public function getRoutes(string ...$classes): array
    {
        $routes = [];

        // Iterate through all the classes
        foreach ($classes as $class) {
            /** @var Attribute[] $attributes */
            $attributes = $this->attributes->forClassAndMembers($class, Attribute::class);

            // Get all the attributes for each class and iterate through them
            foreach ($attributes as $attribute) {
                /** @var ReflectionMethod $reflection */
                $reflection = $attribute->getReflection();
                $method     = $reflection->getName();
                $route      = $this->convertAttributeToData($attribute);

                $route = $this->updateHandler($route, $class, $method);
                $route = $this->updateName($route, $class, $method);
                $route = $this->updateMiddleware($route, $class, $method);
                $route = $this->updateArguments($route, $class, $method);
                $route = $this->updateOptions($route, $class, $method);

                $routes[] = $this->setRouteProperties($route);
            }
        }

        return $routes;
    }

    /**
     * Update the handler for a route.
     *
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     *
     * @throws CliRoutingNoRouteHandlerException
     * @throws ReflectionException
     */
    protected function updateHandler(Route $route, string $class, string $method): Route
    {
        /** @var RouteHandler[] $routeHandlers */
        $routeHandlers = $this->attributes->forMethod($class, $method, RouteHandler::class);
        $routeHandler  = $routeHandlers[0] ?? null;

        // The route attribute's own handler argument supplies a handler too, so
        // the route carries one already unless the author named none
        $handler = $routeHandler->handler ?? $route->getHandler();

        // A route that keeps the stand-in fails only once the command is
        // invoked, so the collector rejects it here instead
        if ($handler instanceof NoRouteHandler) {
            throw new CliRoutingNoRouteHandlerException(
                'Route `' . $route->getName() . '` on ' . $class . '::' . $method
                . ' names no handler'
            );
        }

        return $route->withHandler($handler);
    }

    /**
     * Get a command from an attribute.
     *
     * @param Route $route The attribute
     */
    protected function setRouteProperties(Route $route): Route
    {
        return $route
            ->withArguments(...$route->getArguments())
            ->withOptions(...$route->getOptions());
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
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
            $route = $this->updateProcessExitingMiddleware($route, $middlewareClass);
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
    protected function updateProcessExitingMiddleware(Route $route, string $middleware): Route
    {
        if (is_a($middleware, ProcessExitingMiddlewareContract::class, true)) {
            $route = $route->withAddedProcessExitingMiddleware($middleware);
        }

        return $route;
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     */
    protected function updateArguments(Route $route, string $class, string $method): Route
    {
        $methodParameters = $this->attributes->forMethod($class, $method, ArgumentAttribute::class);

        $route = $route->withArguments(
            ...$this->attributes->forMethodParameters($class, $method, ArgumentAttribute::class),
            ...$methodParameters,
            ...$route->getArguments()
        );

        $parameterAttributes = $route->getArguments();
        $parameters          = [];

        foreach ($parameterAttributes as $parameterAttribute) {
            $parameters[] = $this->convertArgumentAttributeToData($parameterAttribute);
        }

        return $route->withArguments(...$parameters);
    }

    /**
     * @param class-string     $class  The class name
     * @param non-empty-string $method The method name
     */
    protected function updateOptions(Route $route, string $class, string $method): Route
    {
        $methodParameters = $this->attributes->forMethod($class, $method, OptionAttribute::class);

        $route = $route->withOptions(
            ...$this->attributes->forMethodParameters($class, $method, OptionAttribute::class),
            ...$methodParameters,
            ...$route->getOptions()
        );

        $parameterAttributes = $route->getOptions();
        $parameters          = [];

        foreach ($parameterAttributes as $parameterAttribute) {
            $parameters[] = $this->convertOptionAttributeToData($parameterAttribute);
        }

        return $route->withOptions(...$parameters);
    }

    protected function convertAttributeToData(RouteContract $route): Route
    {
        return new Route(
            name: $route->getName(),
            description: $route->getDescription(),
            handler: $route->getHandler(),
            helpText: $route->hasHelpText()
                ? $route->getHelpText()
                : null,
            routeMatchedMiddleware: $route->getRouteMatchedMiddleware(),
            routeDispatchedMiddleware: $route->getRouteDispatchedMiddleware(),
            throwableCaughtMiddleware: $route->getThrowableCaughtMiddleware(),
            processExitingMiddleware: $route->getProcessExitingMiddleware(),
            arguments: $route->getArguments(),
            options: $route->getOptions(),
        );
    }

    protected function convertArgumentAttributeToData(ArgumentParameterContract $parameter): ArgumentParameter
    {
        return new ArgumentParameter(
            name: $parameter->getName(),
            description: $parameter->getDescription(),
            cast: $parameter->hasCast()
                ? $parameter->getCast()
                : null,
            mode: $parameter->getMode(),
            valueMode: $parameter->getValueMode(),
        );
    }

    protected function convertOptionAttributeToData(OptionParameterContract $parameter): OptionParameter
    {
        return new OptionParameter(
            name: $parameter->getName(),
            description: $parameter->getDescription(),
            valueDisplayName: $parameter->getValueDisplayName(),
            cast: $parameter->hasCast()
                ? $parameter->getCast()
                : null,
            defaultValue: $parameter->getDefaultValue(),
            shortNames: $parameter->getShortNames(),
            validValues: $parameter->getValidValues(),
            options: $parameter->getOptions(),
            mode: $parameter->getMode(),
            valueMode: $parameter->getValueMode(),
        );
    }
}
