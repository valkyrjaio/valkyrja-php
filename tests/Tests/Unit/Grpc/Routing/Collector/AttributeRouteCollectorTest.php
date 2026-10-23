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

use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Message\Status\Status;
use Valkyrja\Grpc\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingNoRouteHandlerException;
use Valkyrja\Grpc\Throwable\Exception\GrpcCancelledException;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\AliasedRouteControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\CancellingControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\DeclaredMiddlewareControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\GreeterControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\NoHandlerControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\OverriddenHandlerControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\SingleStageMiddlewareControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\UnattributedControllerFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class AttributeRouteCollectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, CancellationReason}>
     */
    public static function provideCancellingMethods(): iterable
    {
        yield 'client cancelled' => ['/pkg.Cancelling/Cancel', CancellationReason::CLIENT_CANCELLED];

        yield 'deadline exceeded' => ['/pkg.Cancelling/Expire', CancellationReason::DEADLINE_EXCEEDED];
    }

    /**
     * @return iterable<string, array{string, class-string, string}>
     */
    public static function provideSingleStageMethods(): iterable
    {
        yield 'route matched' => [
            '/pkg.SingleStage/Matched',
            RouteMatchedMiddlewareFixture::class,
            'routeMatched',
        ];

        yield 'route dispatched' => [
            '/pkg.SingleStage/Dispatched',
            RouteDispatchedMiddlewareFixture::class,
            'routeDispatched',
        ];

        yield 'throwable caught' => [
            '/pkg.SingleStage/Caught',
            ThrowableCaughtMiddlewareFixture::class,
            'throwableCaught',
        ];

        yield 'sending response' => [
            '/pkg.SingleStage/Sending',
            SendingResponseMiddlewareFixture::class,
            'sendingResponse',
        ];

        yield 'response sent' => [
            '/pkg.SingleStage/Sent',
            ResponseSentMiddlewareFixture::class,
            'responseSent',
        ];
    }

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

    public function testCollectsARouteForEachRouteAttribute(): void
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

    public function testCarriesTheHandlerTheRouteAttributeNames(): void
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

    public function testAHandlerAttributeAloneNamesTheHandler(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(OverriddenHandlerControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Overridden/DoInstanceThing');

        // The route attribute names no handler, so the handler attribute is the only spelling that
        // supplies one. The attributed method is an instance method, which the collector never
        // calls.
        $handler = $route->getHandler();

        self::assertSame(
            [OverriddenHandlerControllerFixture::OVERRIDDEN],
            $handler(new Container(), $route)->getMessages()
        );
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

    /**
     * @param class-string     $middleware The middleware
     * @param non-empty-string $stage      The stage the middleware's contract names
     */
    #[DataProvider('provideSingleStageMethods')]
    public function testAMiddlewareLandsOnlyInTheStageItsContractNames(string $method, string $middleware, string $stage): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(SingleStageMiddlewareControllerFixture::class);
        $route  = self::byMethod($routes, $method);

        /** @var array<non-empty-string, class-string[]> $expected */
        $expected = [
            'routeMatched'    => [],
            'routeDispatched' => [],
            'throwableCaught' => [],
            'sendingResponse' => [],
            'responseSent'    => [],
        ];

        $expected[$stage] = [$middleware];

        // Every bucket is asserted, so a stage read from the wrong contract fails here, and so does
        // a stage that accepts a second contract as well as its own.
        self::assertSame($expected, [
            'routeMatched'    => $route->getRouteMatchedMiddleware(),
            'routeDispatched' => $route->getRouteDispatchedMiddleware(),
            'throwableCaught' => $route->getThrowableCaughtMiddleware(),
            'sendingResponse' => $route->getSendingResponseMiddleware(),
            'responseSent'    => $route->getResponseSentMiddleware(),
        ]);
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

        self::assertCount(5, $routes);
    }

    public function testWithNoControllers(): void
    {
        self::assertSame([], new AttributeRouteCollector()->getRoutes());
    }

    public function testARouteThatNamesNoHandlerIsRejectedAtScanTime(): void
    {
        $this->expectException(GrpcRoutingNoRouteHandlerException::class);
        $this->expectExceptionMessage(
            'Route `DoThing` on ' . NoHandlerControllerFixture::class . '::doThing names no handler'
        );

        new AttributeRouteCollector()->getRoutes(NoHandlerControllerFixture::class);
    }

    public function testCarriesTheMessageTypesTheRouteAttributeNames(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(DeclaredMiddlewareControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Declared/EveryStage');

        self::assertSame(ServiceResponse::class, $route->getRequestType());
        self::assertSame(Status::class, $route->getResponseType());
    }

    public function testCarriesEveryMiddlewareStageListTheRouteAttributeNames(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(DeclaredMiddlewareControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Declared/EveryStage');

        // Each stage names its own class, so an argument wired to the wrong stage fails here.
        self::assertSame([RouteMatchedMiddlewareFixture::class], $route->getRouteMatchedMiddleware());
        self::assertSame([RouteDispatchedMiddlewareFixture::class], $route->getRouteDispatchedMiddleware());
        self::assertSame([ThrowableCaughtMiddlewareFixture::class], $route->getThrowableCaughtMiddleware());
        self::assertSame([SendingResponseMiddlewareFixture::class], $route->getSendingResponseMiddleware());
        self::assertSame([ResponseSentMiddlewareFixture::class], $route->getResponseSentMiddleware());
    }

    public function testARepeatedRouteAttributeCollectsARouteForEachName(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(AliasedRouteControllerFixture::class);

        self::assertCount(2, $routes);

        $first  = self::byMethod($routes, '/pkg.Aliased/SayHello');
        $second = self::byMethod($routes, '/pkg.Aliased/Greet');

        // One method answers two remote methods, and each route carries its own field set.
        self::assertFalse($first->isServerStreaming());
        self::assertTrue($second->isServerStreaming());
        self::assertSame(['hello'], ($first->getHandler())(new Container(), $first)->getMessages());
        self::assertSame(['hello'], ($second->getHandler())(new Container(), $second)->getMessages());
    }

    public function testCarriesTheHandlerTheRouteAttributeNamesRatherThanTheAttributedMethod(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(DeclaredMiddlewareControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Declared/EveryStage');

        $handler = $route->getHandler();

        self::assertSame(
            [DeclaredMiddlewareControllerFixture::DECLARED],
            $handler(new Container(), $route)->getMessages()
        );
    }

    public function testAMiddlewareAttributeAppendsToTheStageListTheRouteAttributeNames(): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(DeclaredMiddlewareControllerFixture::class);
        $route  = self::byMethod($routes, '/pkg.Declared/Appended');

        // The argument already names the attributed middleware, and the stage list keeps both, so
        // a collector that deduplicated would fail here.
        self::assertSame(
            [
                RouteMatchedMiddlewareFixture::class,
                AllMiddlewareFixture::class,
                AllMiddlewareFixture::class,
            ],
            $route->getRouteMatchedMiddleware()
        );
        self::assertSame([AllMiddlewareFixture::class], $route->getResponseSentMiddleware());
    }

    #[DataProvider('provideCancellingMethods')]
    public function testTheHandlerRethrowsTheControllersOwnCancellation(string $method, CancellationReason $reason): void
    {
        $routes = new AttributeRouteCollector()->getRoutes(CancellingControllerFixture::class);
        $route  = self::byMethod($routes, $method);

        $handler = $route->getHandler();

        try {
            $handler(new Container(), $route);

            self::fail('The cancellation must reach the caller.');
        } catch (GrpcCancelledException $exception) {
            // The collector must not wrap the handler it collected, or the status mapping cannot
            // see the cancellation.
            self::assertSame($reason, $exception->getReason());
        }
    }
}
