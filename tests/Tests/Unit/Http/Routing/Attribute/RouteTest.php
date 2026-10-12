<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Http\Routing\Attribute;

use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Support\NoRouteHandler;
use Valkyrja\Tests\Fixtures\Http\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Struct\QueryRequestStructEnum;
use Valkyrja\Tests\Fixtures\Http\Struct\ResponseStructEnum;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the route attribute.
 */
final class RouteTest extends TestCase
{
    public function testDefaults(): void
    {
        $route = new Route(
            path: '/',
            name: 'test'
        );

        self::assertSame('/', $route->getPath());
        self::assertSame('test', $route->getName());
        self::assertContains(RequestMethod::HEAD, $route->getRequestMethods());
        self::assertContains(RequestMethod::GET, $route->getRequestMethods());
        self::assertNotContains(RequestMethod::POST, $route->getRequestMethods());
        self::assertNotContains(RequestMethod::PUT, $route->getRequestMethods());
        self::assertNotContains(RequestMethod::PATCH, $route->getRequestMethods());
        self::assertNotContains(RequestMethod::TRACE, $route->getRequestMethods());
        self::assertNotContains(RequestMethod::OPTIONS, $route->getRequestMethods());
        self::assertNotContains(RequestMethod::DELETE, $route->getRequestMethods());
        self::assertNotContains(RequestMethod::CONNECT, $route->getRequestMethods());
    }

    public function testTheDefaultHandlerIsRecognizable(): void
    {
        $route = new Route(path: '/', name: 'test');

        // A closure cannot be told apart from any other, so the stand-in is an
        // object a caller can test for
        self::assertInstanceOf(NoRouteHandler::class, $route->getHandler());
    }

    public function testPath(): void
    {
        $value = '/test';
        $route = new Route(path: $value, name: 'test');

        self::assertSame($value, $route->getPath());
    }

    public function testName(): void
    {
        $value = 'test';
        $route = new Route(path: '/', name: $value);

        self::assertSame($value, $route->getName());
    }

    public function testMethods(): void
    {
        $value = [
            RequestMethod::POST,
        ];
        $route = new Route(path: '/', name: 'test', requestMethods: $value);

        self::assertSame($value, $route->getRequestMethods());
    }

    public function testRequestStruct(): void
    {
        $value = QueryRequestStructEnum::first;
        $route = new Route(path: '/', name: 'test', requestStruct: $value);

        self::assertSame($value, $route->getRequestStruct());
    }

    public function testResponseStruct(): void
    {
        $value = ResponseStructEnum::first;
        $route = new Route(path: '/', name: 'test', responseStruct: $value);

        self::assertSame($value, $route->getResponseStruct());
    }

    public function testMatchedMiddleware(): void
    {
        $value = [RouteMatchedMiddlewareFixture::class];
        $route = new Route(path: '/', name: 'test', routeMatchedMiddleware: $value);

        self::assertSame($value, $route->getRouteMatchedMiddleware());
    }

    public function testDispatchedMiddleware(): void
    {
        $value = [RouteDispatchedMiddlewareFixture::class];
        $route = new Route(path: '/', name: 'test', routeDispatchedMiddleware: $value);

        self::assertSame($value, $route->getRouteDispatchedMiddleware());
    }

    public function testExceptionMiddleware(): void
    {
        $value = [ThrowableCaughtMiddlewareFixture::class];
        $route = new Route(path: '/', name: 'test', throwableCaughtMiddleware: $value);

        self::assertSame($value, $route->getThrowableCaughtMiddleware());
    }

    public function testSendingMiddleware(): void
    {
        $value = [SendingResponseMiddlewareFixture::class];
        $route = new Route(path: '/', name: 'test', sendingResponseMiddleware: $value);

        self::assertSame($value, $route->getSendingResponseMiddleware());
    }

    public function testResponseSentMiddleware(): void
    {
        $value = [ResponseSentMiddlewareFixture::class];
        $route = new Route(path: '/', name: 'test', responseSentMiddleware: $value);

        self::assertSame($value, $route->getResponseSentMiddleware());
    }
}
