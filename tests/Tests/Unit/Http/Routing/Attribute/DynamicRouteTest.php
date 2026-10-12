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
use Valkyrja\Http\Routing\Attribute\DynamicRoute;
use Valkyrja\Http\Routing\Constant\Regex;
use Valkyrja\Http\Routing\Data\Parameter;
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
final class DynamicRouteTest extends TestCase
{
    public function testDefaults(): void
    {
        $route = new DynamicRoute(
            path: '/',
            name: 'test',
            parameters: [],
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
        $route = new DynamicRoute(path: '/', name: 'test', parameters: []);

        self::assertInstanceOf(NoRouteHandler::class, $route->getHandler());
    }

    public function testPath(): void
    {
        $value = '/test';
        $route = new DynamicRoute(path: $value, name: 'test', parameters: []);

        self::assertSame($value, $route->getPath());
    }

    public function testName(): void
    {
        $value = 'test';
        $route = new DynamicRoute(path: '/', name: $value, parameters: []);

        self::assertSame($value, $route->getName());
    }

    public function testMethods(): void
    {
        $value = [
            RequestMethod::POST,
        ];
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], requestMethods: $value);

        self::assertSame($value, $route->getRequestMethods());
    }

    public function testParameters(): void
    {
        $value = [
            new Parameter(name: 'test', regex: Regex::ALPHA),
        ];
        $route = new DynamicRoute(path: '/', name: 'test', parameters: $value);

        self::assertSame($value, $route->getParameters());
    }

    public function testHandler(): void
    {
        $value = static fn (): null => null;
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], handler: $value);

        self::assertSame($value, $route->getHandler());
    }

    public function testRequestStruct(): void
    {
        $value = QueryRequestStructEnum::first;
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], requestStruct: $value);

        self::assertSame($value, $route->getRequestStruct());
    }

    public function testResponseStruct(): void
    {
        $value = ResponseStructEnum::first;
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], responseStruct: $value);

        self::assertSame($value, $route->getResponseStruct());
    }

    public function testMatchedMiddleware(): void
    {
        $value = [RouteMatchedMiddlewareFixture::class];
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], routeMatchedMiddleware: $value);

        self::assertSame($value, $route->getRouteMatchedMiddleware());
    }

    public function testDispatchedMiddleware(): void
    {
        $value = [RouteDispatchedMiddlewareFixture::class];
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], routeDispatchedMiddleware: $value);

        self::assertSame($value, $route->getRouteDispatchedMiddleware());
    }

    public function testExceptionMiddleware(): void
    {
        $value = [ThrowableCaughtMiddlewareFixture::class];
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], throwableCaughtMiddleware: $value);

        self::assertSame($value, $route->getThrowableCaughtMiddleware());
    }

    public function testSendingMiddleware(): void
    {
        $value = [SendingResponseMiddlewareFixture::class];
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], sendingResponseMiddleware: $value);

        self::assertSame($value, $route->getSendingResponseMiddleware());
    }

    public function testResponseSentMiddleware(): void
    {
        $value = [ResponseSentMiddlewareFixture::class];
        $route = new DynamicRoute(path: '/', name: 'test', parameters: [], responseSentMiddleware: $value);

        self::assertSame($value, $route->getResponseSentMiddleware());
    }
}
