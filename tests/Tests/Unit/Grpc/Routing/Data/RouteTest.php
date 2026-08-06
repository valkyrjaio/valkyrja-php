<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Data;

use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingInvalidMethodException;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class RouteTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidMethods(): iterable
    {
        yield 'no leading slash' => ['pkg.Service/Method'];

        yield 'only one slash' => ['/pkg.ServiceMethod'];

        yield 'empty method name' => ['/pkg.Service/'];

        yield 'empty service' => ['//Method'];

        yield 'empty' => ['/'];
    }

    /**
     * @return iterable<string, array{string, string, class-string}>
     */
    public static function provideMiddlewareStages(): iterable
    {
        yield 'route matched' => ['RouteMatchedMiddleware', 'getRouteMatchedMiddleware', RouteMatchedMiddlewareFixture::class];

        yield 'route dispatched' => ['RouteDispatchedMiddleware', 'getRouteDispatchedMiddleware', RouteDispatchedMiddlewareFixture::class];

        yield 'throwable caught' => ['ThrowableCaughtMiddleware', 'getThrowableCaughtMiddleware', ThrowableCaughtMiddlewareFixture::class];

        yield 'sending response' => ['SendingResponseMiddleware', 'getSendingResponseMiddleware', SendingResponseMiddlewareFixture::class];

        yield 'response sent' => ['ResponseSentMiddleware', 'getResponseSentMiddleware', ResponseSentMiddlewareFixture::class];
    }

    private static function handler(): callable
    {
        return static fn (): ServiceResponseContract => ServiceResponse::ok();
    }

    public function testSplitsTheFullyQualifiedMethod(): void
    {
        $route = new Route('/pkg.sub.Service/DoThing', self::handler());

        self::assertSame('/pkg.sub.Service/DoThing', $route->getMethod());
        self::assertSame('pkg.sub.Service', $route->getService());
        self::assertSame('DoThing', $route->getMethodName());
    }

    #[DataProvider('provideInvalidMethods')]
    public function testRejectsAMalformedMethod(string $method): void
    {
        $this->expectException(GrpcRoutingInvalidMethodException::class);

        new Route($method, self::handler());
    }

    public function testDefaults(): void
    {
        $route = new Route('/pkg.Service/Method', self::handler());

        self::assertNull($route->getRequestType());
        self::assertNull($route->getResponseType());
        self::assertFalse($route->isClientStreaming());
        self::assertFalse($route->isServerStreaming());
        self::assertSame([], $route->getRouteMatchedMiddleware());
        self::assertSame([], $route->getRouteDispatchedMiddleware());
        self::assertSame([], $route->getThrowableCaughtMiddleware());
        self::assertSame([], $route->getSendingResponseMiddleware());
        self::assertSame([], $route->getResponseSentMiddleware());
    }

    public function testTheHandlerIsInvokable(): void
    {
        $container = new Container();
        $route     = new Route('/pkg.Service/Method', self::handler());

        $handler = $route->getHandler();

        self::assertInstanceOf(ServiceResponseContract::class, $handler($container, $route));
    }

    public function testWithHandler(): void
    {
        $route = new Route('/pkg.Service/Method', self::handler());

        $replacement = static fn (): ServiceResponseContract => ServiceResponse::unimplemented();

        $new = $route->withHandler($replacement);

        self::assertNotSame($route, $new);
        self::assertSame($replacement, $new->getHandler());
    }

    public function testWithRequestType(): void
    {
        $route = new Route('/pkg.Service/Method', self::handler());
        $new   = $route->withRequestType(Route::class);

        self::assertNotSame($route, $new);
        self::assertNull($route->getRequestType());
        self::assertSame(Route::class, $new->getRequestType());
        self::assertNull($new->withRequestType(null)->getRequestType());
    }

    public function testWithResponseType(): void
    {
        $route = new Route('/pkg.Service/Method', self::handler());
        $new   = $route->withResponseType(Route::class);

        self::assertNotSame($route, $new);
        self::assertNull($route->getResponseType());
        self::assertSame(Route::class, $new->getResponseType());
        self::assertNull($new->withResponseType(null)->getResponseType());
    }

    public function testWithClientStreaming(): void
    {
        $route = new Route('/pkg.Service/Method', self::handler());
        $new   = $route->withClientStreaming(true);

        self::assertNotSame($route, $new);
        self::assertFalse($route->isClientStreaming());
        self::assertTrue($new->isClientStreaming());
    }

    public function testWithServerStreaming(): void
    {
        $route = new Route('/pkg.Service/Method', self::handler());
        $new   = $route->withServerStreaming(true);

        self::assertNotSame($route, $new);
        self::assertFalse($route->isServerStreaming());
        self::assertTrue($new->isServerStreaming());
    }

    /**
     * @param class-string $middleware
     */
    #[DataProvider('provideMiddlewareStages')]
    public function testWithMiddlewareReplaces(string $stage, string $getter, string $middleware): void
    {
        $route = new Route('/pkg.Service/Method', self::handler());

        /** @var RouteContract $seeded */
        $seeded = $route->{"with$stage"}(CallReceivedMiddlewareFixture::class);
        /** @var RouteContract $new */
        $new = $seeded->{"with$stage"}($middleware);

        self::assertNotSame($seeded, $new);
        self::assertSame([CallReceivedMiddlewareFixture::class], $seeded->{$getter}());
        self::assertSame([$middleware], $new->{$getter}());
    }

    /**
     * @param class-string $middleware
     */
    #[DataProvider('provideMiddlewareStages')]
    public function testWithAddedMiddlewareAppends(string $stage, string $getter, string $middleware): void
    {
        $route = new Route('/pkg.Service/Method', self::handler());

        /** @var RouteContract $seeded */
        $seeded = $route->{"with$stage"}($middleware);
        /** @var RouteContract $new */
        $new = $seeded->{"withAdded$stage"}($middleware);

        self::assertNotSame($seeded, $new);
        self::assertSame([$middleware], $seeded->{$getter}());
        // Middleware is appended, never deduplicated: a duplicate is the developer's bug.
        self::assertSame([$middleware, $middleware], $new->{$getter}());
    }
}
