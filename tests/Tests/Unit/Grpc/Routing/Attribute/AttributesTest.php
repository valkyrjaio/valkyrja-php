<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Attribute;

use ReflectionMethod;
use Valkyrja\Grpc\Routing\Attribute\Route;
use Valkyrja\Grpc\Routing\Attribute\Route\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Route as DataRoute;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\GreeterControllerFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class AttributesTest extends TestCase
{
    public function testService(): void
    {
        self::assertSame('pkg.Greeter', new Service('pkg.Greeter')->service);
    }

    public function testRouteDefaults(): void
    {
        $route = new Route('SayHello');

        self::assertSame('SayHello', $route->name);
        self::assertNull($route->handler);
        self::assertFalse($route->clientStreaming);
        self::assertFalse($route->serverStreaming);
        self::assertNull($route->requestType);
        self::assertNull($route->responseType);
        self::assertSame([], $route->routeMatchedMiddleware);
        self::assertSame([], $route->routeDispatchedMiddleware);
        self::assertSame([], $route->throwableCaughtMiddleware);
        self::assertSame([], $route->sendingResponseMiddleware);
        self::assertSame([], $route->responseSentMiddleware);
    }

    public function testRouteWithEverything(): void
    {
        $handler = [GreeterControllerFixture::class, 'sayHello'];

        $route = new Route(
            name: 'Chat',
            handler: $handler,
            requestType: DataRoute::class,
            responseType: Service::class,
            clientStreaming: true,
            serverStreaming: true,
            routeMatchedMiddleware: [RouteMatchedMiddlewareFixture::class],
            routeDispatchedMiddleware: [RouteDispatchedMiddlewareFixture::class],
            throwableCaughtMiddleware: [ThrowableCaughtMiddlewareFixture::class],
            sendingResponseMiddleware: [SendingResponseMiddlewareFixture::class],
            responseSentMiddleware: [ResponseSentMiddlewareFixture::class],
        );

        self::assertSame($handler, $route->handler);
        self::assertTrue($route->clientStreaming);
        self::assertTrue($route->serverStreaming);
        self::assertSame(DataRoute::class, $route->requestType);
        self::assertSame(Service::class, $route->responseType);
        self::assertSame([RouteMatchedMiddlewareFixture::class], $route->routeMatchedMiddleware);
        self::assertSame([RouteDispatchedMiddlewareFixture::class], $route->routeDispatchedMiddleware);
        self::assertSame([ThrowableCaughtMiddlewareFixture::class], $route->throwableCaughtMiddleware);
        self::assertSame([SendingResponseMiddlewareFixture::class], $route->sendingResponseMiddleware);
        self::assertSame([ResponseSentMiddlewareFixture::class], $route->responseSentMiddleware);
    }

    public function testRouteCarriesItsReflection(): void
    {
        $route      = new Route('SayHello');
        $reflection = new ReflectionMethod(GreeterControllerFixture::class, 'sayHello');

        $route->setReflection($reflection);

        self::assertSame($reflection, $route->getReflection());
    }

    public function testMiddleware(): void
    {
        self::assertSame(AllMiddlewareFixture::class, new Middleware(AllMiddlewareFixture::class)->name);
    }

    public function testRouteHandler(): void
    {
        $handler = [GreeterControllerFixture::class, 'sayHello'];

        self::assertSame($handler, new RouteHandler($handler)->handler);
    }
}
