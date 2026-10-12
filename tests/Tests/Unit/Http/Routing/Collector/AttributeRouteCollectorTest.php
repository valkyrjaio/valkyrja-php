<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Http\Routing\Collector;

use ReflectionException;
use Valkyrja\Http\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Http\Routing\Data\Contract\DynamicRouteContract;
use Valkyrja\Http\Routing\Throwable\Exception\HttpRoutingNoRouteHandlerException;
use Valkyrja\Tests\Fixtures\Http\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Routing\Controller\ControllerAttributedFixture;
use Valkyrja\Tests\Fixtures\Http\Routing\Controller\ControllerFixture;
use Valkyrja\Tests\Fixtures\Http\Routing\Controller\ControllerMissingHandlerFixture;
use Valkyrja\Tests\Fixtures\Http\Routing\Controller\ControllerPartialHandlerFixture;
use Valkyrja\Tests\Fixtures\Http\Routing\Controller\ControllerWithAllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Routing\Controller\RoutingCombinationsControllerFixture;
use Valkyrja\Tests\Fixtures\Http\Routing\Provider\RouteProviderFixture;
use Valkyrja\Tests\Fixtures\Http\Struct\IndexedJsonRequestStructEnum;
use Valkyrja\Tests\Fixtures\Http\Struct\ResponseStructEnum;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the AttributeRouteCollector service.
 */
final class AttributeRouteCollectorTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testGetRoutes(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(ControllerFixture::class);

        self::assertCount(3, $routes);

        $welcomeRoute = $routes[0];

        self::assertSame(ControllerFixture::WELCOME_PATH, $welcomeRoute->getPath());
        self::assertSame(ControllerFixture::WELCOME_NAME, $welcomeRoute->getName());

        $parametersRoute = $routes[1];

        self::assertInstanceOf(DynamicRouteContract::class, $parametersRoute);
        self::assertSame(ControllerFixture::PARAMETERS_PATH, $parametersRoute->getPath());
        self::assertSame(ControllerFixture::PARAMETERS_NAME, $parametersRoute->getName());
        self::assertSame([RouteProviderFixture::class, 'handler'], $parametersRoute->getHandler());
        self::assertSame('/^\/parameters\/(?<name>[a-zA-Z]+)$/', $parametersRoute->getRegex());
        self::assertSame([RouteDispatchedMiddlewareFixture::class], $parametersRoute->getRouteDispatchedMiddleware());
        self::assertSame([RouteMatchedMiddlewareFixture::class], $parametersRoute->getRouteMatchedMiddleware());
        self::assertSame([SendingResponseMiddlewareFixture::class], $parametersRoute->getSendingResponseMiddleware());
        self::assertSame([ResponseSentMiddlewareFixture::class], $parametersRoute->getResponseSentMiddleware());
        self::assertSame([ThrowableCaughtMiddlewareFixture::class], $parametersRoute->getThrowableCaughtMiddleware());
        self::assertSame(IndexedJsonRequestStructEnum::first, $parametersRoute->getRequestStruct());
        self::assertSame(ResponseStructEnum::first, $parametersRoute->getResponseStruct());
        self::assertCount(1, $parametersRoute->getParameters());
        self::assertTrue($parametersRoute->getParameters()[0]->hasCast());
        self::assertSame(ControllerFixture::PARAMETERS_PARAMETER_NAME, $parametersRoute->getParameters()[0]->getName());

        $dynamicRoute = $routes[2];

        self::assertInstanceOf(DynamicRouteContract::class, $dynamicRoute);
        self::assertSame(ControllerFixture::DYNAMIC_PATH, $dynamicRoute->getPath());
        self::assertSame(ControllerFixture::DYNAMIC_NAME, $dynamicRoute->getName());
        self::assertSame('/^\/dynamic\/(?<foo>[a-zA-Z]+)\/(?<bar>[a-zA-Z]+)$/', $dynamicRoute->getRegex());
        self::assertSame([RouteDispatchedMiddlewareFixture::class], $dynamicRoute->getRouteDispatchedMiddleware());
        self::assertSame([RouteMatchedMiddlewareFixture::class], $dynamicRoute->getRouteMatchedMiddleware());
        self::assertSame([SendingResponseMiddlewareFixture::class], $dynamicRoute->getSendingResponseMiddleware());
        self::assertSame([ResponseSentMiddlewareFixture::class], $dynamicRoute->getResponseSentMiddleware());
        self::assertSame([ThrowableCaughtMiddlewareFixture::class], $dynamicRoute->getThrowableCaughtMiddleware());
        self::assertSame(IndexedJsonRequestStructEnum::first, $dynamicRoute->getRequestStruct());
        self::assertSame(ResponseStructEnum::first, $dynamicRoute->getResponseStruct());
        self::assertCount(2, $dynamicRoute->getParameters());
        self::assertTrue($dynamicRoute->getParameters()[0]->hasCast());
        self::assertTrue($dynamicRoute->getParameters()[1]->hasCast());
        self::assertSame(ControllerFixture::DYNAMIC_PARAMETER_NAME, $dynamicRoute->getParameters()[0]->getName());
        self::assertSame(ControllerFixture::DYNAMIC_PARAMETER_NAME2, $dynamicRoute->getParameters()[1]->getName());
    }

    public function testRejectsARouteThatNamesNoHandler(): void
    {
        // Without this the route would keep the attribute's stand-in and fail
        // only once a request reached it, long after the author could act on it
        $this->expectException(HttpRoutingNoRouteHandlerException::class);
        $this->expectExceptionMessage(
            'Route `no-handler` on ' . ControllerMissingHandlerFixture::class . '::noHandler names no handler'
        );

        new AttributeRouteCollector()->getRoutes(ControllerMissingHandlerFixture::class);
    }

    /**
     * @throws ReflectionException
     */
    public function testNamesTheRejectedRouteWhenTheMethodRepeatsTheAttribute(): void
    {
        // One method declares a route for each attribute it repeats, so the class
        // and the method alone would not say which of them names no handler
        $this->expectException(HttpRoutingNoRouteHandlerException::class);
        $this->expectExceptionMessage(
            'Route `' . ControllerPartialHandlerFixture::BARE_NAME . '` on '
            . ControllerPartialHandlerFixture::class . '::repeated names no handler'
        );

        new AttributeRouteCollector()->getRoutes(ControllerPartialHandlerFixture::class);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetRoutesWithControllerAttributes(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(ControllerAttributedFixture::class);

        self::assertCount(2, $routes);

        $welcomeRoute = $routes[0];

        self::assertSame('/controller/welcome/path', $welcomeRoute->getPath());
        self::assertSame('controller.' . ControllerAttributedFixture::WELCOME_NAME . '.name', $welcomeRoute->getName());
        // Each route names its handler through the attribute's own argument and
        // carries no RouteHandler attribute, so the collector keeps both
        self::assertSame([ControllerAttributedFixture::class, 'welcomeHandler'], $welcomeRoute->getHandler());

        $showRoute = $routes[1];

        self::assertInstanceOf(DynamicRouteContract::class, $showRoute);
        self::assertSame('/controller/show/{id}', $showRoute->getPath());
        self::assertSame([ControllerAttributedFixture::class, 'showHandler'], $showRoute->getHandler());
    }

    /**
     * @throws ReflectionException
     */
    public function testGetRoutesWithSingleMiddlewareThatHasAllTypes(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(ControllerWithAllMiddlewareFixture::class);

        self::assertCount(2, $routes);

        $route = $routes[0];

        self::assertSame(ControllerWithAllMiddlewareFixture::WELCOME_PATH, $route->getPath());
        self::assertSame(ControllerWithAllMiddlewareFixture::WELCOME_NAME, $route->getName());
        self::assertSame([AllMiddlewareFixture::class], $route->getRouteDispatchedMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getRouteMatchedMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getSendingResponseMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getResponseSentMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getThrowableCaughtMiddleware());
        self::assertSame(IndexedJsonRequestStructEnum::first, $route->getRequestStruct());
        self::assertSame(ResponseStructEnum::first, $route->getResponseStruct());

        $dynamicRoute = $routes[1];

        self::assertSame(ControllerWithAllMiddlewareFixture::DYNAMIC_PATH, $dynamicRoute->getPath());
        self::assertSame(ControllerWithAllMiddlewareFixture::DYNAMIC_NAME, $dynamicRoute->getName());
        self::assertSame([AllMiddlewareFixture::class], $dynamicRoute->getRouteDispatchedMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $dynamicRoute->getRouteMatchedMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $dynamicRoute->getSendingResponseMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $dynamicRoute->getResponseSentMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $dynamicRoute->getThrowableCaughtMiddleware());
        self::assertSame(IndexedJsonRequestStructEnum::first, $dynamicRoute->getRequestStruct());
        self::assertSame(ResponseStructEnum::first, $dynamicRoute->getResponseStruct());
    }

    /**
     * The attribute construction path must produce the same regex, across a matrix of
     * parameter types and modifiers, as direct construction does through the Processor.
     *
     * @throws ReflectionException
     */
    public function testGetRoutesProducesExpectedRegexForCombinations(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(RoutingCombinationsControllerFixture::class);

        $byName = [];

        foreach ($routes as $route) {
            $byName[$route->getName()] = $route;
        }

        $expected = [
            RoutingCombinationsControllerFixture::NUM_NAME         => [RoutingCombinationsControllerFixture::NUM_PATH, RoutingCombinationsControllerFixture::NUM_REGEX],
            RoutingCombinationsControllerFixture::SLUG_NAME        => [RoutingCombinationsControllerFixture::SLUG_PATH, RoutingCombinationsControllerFixture::SLUG_REGEX],
            RoutingCombinationsControllerFixture::OPTIONAL_NAME    => [RoutingCombinationsControllerFixture::OPTIONAL_PATH, RoutingCombinationsControllerFixture::OPTIONAL_REGEX],
            RoutingCombinationsControllerFixture::NON_CAPTURE_NAME => [RoutingCombinationsControllerFixture::NON_CAPTURE_PATH, RoutingCombinationsControllerFixture::NON_CAPTURE_REGEX],
            RoutingCombinationsControllerFixture::MULTI_NAME       => [RoutingCombinationsControllerFixture::MULTI_PATH, RoutingCombinationsControllerFixture::MULTI_REGEX],
        ];

        self::assertCount(5, $routes);

        foreach ($expected as $name => [$path, $regex]) {
            self::assertArrayHasKey($name, $byName);

            $route = $byName[$name];

            self::assertInstanceOf(DynamicRouteContract::class, $route);
            self::assertSame($path, $route->getPath());
            self::assertSame($regex, $route->getRegex());
        }
    }
}
