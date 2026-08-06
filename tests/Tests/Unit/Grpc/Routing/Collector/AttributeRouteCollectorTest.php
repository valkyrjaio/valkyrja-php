<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Collector;

use Valkyrja\Container\Manager\Container;
use Valkyrja\Grpc\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingInvalidHandlerException;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\BadHandlerControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\GreeterControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\OverriddenHandlerControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\UnattributedControllerFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class AttributeRouteCollectorTest extends TestCase
{
    /**
     * @param RouteContract[] $routes
     */
    private static function byMethod(array $routes, string $method): RouteContract
    {
        foreach ($routes as $route) {
            if ($route->getMethod() === $method) {
                return $route;
            }
        }

        self::fail("No route collected for `$method`");
    }

    public function testCollectsOneRoutePerAttributedMethod(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(GreeterControllerFixture::class);

        self::assertCount(3, $routes);
    }

    public function testKeysTheRouteByServiceAndMethodName(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(GreeterControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Greeter/SayHello');

        self::assertSame('pkg.Greeter', $route->getService());
        self::assertSame('SayHello', $route->getMethodName());
    }

    public function testCarriesTheStreamingFlags(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(GreeterControllerFixture::class);

        $unary = self::byMethod($routes, '/pkg.Greeter/SayHello');

        self::assertFalse($unary->isClientStreaming());
        self::assertFalse($unary->isServerStreaming());

        $bidirectional = self::byMethod($routes, '/pkg.Greeter/Chat');

        self::assertTrue($bidirectional->isClientStreaming());
        self::assertTrue($bidirectional->isServerStreaming());
    }

    public function testWiresTheAttributedMethodAsTheHandler(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(GreeterControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Greeter/SayHello');

        $handler  = $route->getHandler();
        $response = $handler(new Container(), $route);

        self::assertSame(['hello'], $response->getMessages());
    }

    public function testAnExplicitHandlerAttributeWins(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(OverriddenHandlerControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Overridden/DoThing');

        $handler  = $route->getHandler();
        $response = $handler(new Container(), $route);

        self::assertSame([OverriddenHandlerControllerFixture::OVERRIDDEN], $response->getMessages());
    }

    public function testAMiddlewareLandsInEveryStageItImplements(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(GreeterControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Greeter/Guarded');

        self::assertSame([AllMiddlewareFixture::class], $route->getRouteMatchedMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getRouteDispatchedMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getThrowableCaughtMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getSendingResponseMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getResponseSentMiddleware());
    }

    public function testAMethodWithoutMiddlewareGetsNone(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(GreeterControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Greeter/SayHello');

        self::assertSame([], $route->getRouteMatchedMiddleware());
        self::assertSame([], $route->getResponseSentMiddleware());
    }

    public function testSkipsAClassWithoutAServiceAttribute(): void
    {
        self::assertSame([], new AttributeRouteCollector()->getRoutes(UnattributedControllerFixture::class));
    }

    public function testCollectsAcrossSeveralControllers(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(
            GreeterControllerFixture::class,
            OverriddenHandlerControllerFixture::class
        );

        self::assertCount(4, $routes);
    }

    public function testWithNoControllers(): void
    {
        self::assertSame([], new AttributeRouteCollector()->getRoutes());
    }

    public function testAHandlerThatDoesNotReturnAResponseIsRejected(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(BadHandlerControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Bad/DoThing');

        $handler = $route->getHandler();

        $this->expectException(GrpcRoutingInvalidHandlerException::class);

        $handler(new Container(), $route);
    }
}
