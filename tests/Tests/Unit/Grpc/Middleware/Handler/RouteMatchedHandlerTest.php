<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Middleware\Handler;

use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler\RouteMatchedHandlerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareCancellingFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;

/**
 * Test the route matched handler.
 */
final class RouteMatchedHandlerTest extends HandlerTestCase
{
    public function testWithDefaults(): void
    {
        $handler = new RouteMatchedHandlerFixture($this->container);

        self::assertSame($this->route, $handler->routeMatched($this->call, $this->route));
        self::assertSame(1, $handler->getCount());
    }

    public function testWalksTheChain(): void
    {
        RouteMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture(
            $this->container,
            RouteMatchedMiddlewareFixture::class,
            RouteMatchedMiddlewareFixture::class
        );

        self::assertSame($this->route, $handler->routeMatched($this->call, $this->route));
        self::assertSame(3, $handler->getCount());
        self::assertSame(2, RouteMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testAdd(): void
    {
        RouteMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture($this->container);

        $handler->add(RouteMatchedMiddlewareFixture::class);

        self::assertSame($this->route, $handler->routeMatched($this->call, $this->route));
        self::assertSame(1, RouteMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingMiddlewareSkipsTheRemainder(): void
    {
        RouteMatchedMiddlewareChangedFixture::resetCounter();
        RouteMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture(
            $this->container,
            RouteMatchedMiddlewareChangedFixture::class,
            RouteMatchedMiddlewareFixture::class
        );

        $result = $handler->routeMatched($this->call, $this->route);

        self::assertInstanceOf(ServiceResponseContract::class, $result);
        self::assertSame(StatusCode::ABORTED, $result->getStatus()->getCode());
        self::assertSame(1, RouteMatchedMiddlewareChangedFixture::getAndResetCounter());
        self::assertSame(0, RouteMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testThePreCheckFastExitsAnAlreadyCancelledCall(): void
    {
        RouteMatchedMiddlewareFixture::resetCounter();

        $this->cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $handler = new RouteMatchedHandlerFixture(
            $this->container,
            RouteMatchedMiddlewareFixture::class
        );

        $result = $handler->routeMatched($this->call, $this->route);

        self::assertInstanceOf(ServiceResponseContract::class, $result);
        self::assertSame(StatusCode::CANCELLED, $result->getStatus()->getCode());
        self::assertSame(0, RouteMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testThePostCheckPassesAReturnedCancellationThrough(): void
    {
        RouteMatchedMiddlewareCancelledFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture(
            $this->container,
            RouteMatchedMiddlewareCancelledFixture::class
        );

        $result = $handler->routeMatched($this->call, $this->route);

        self::assertInstanceOf(ServiceResponseContract::class, $result);
        self::assertSame(StatusCode::CANCELLED, $result->getStatus()->getCode());
        self::assertSame(1, RouteMatchedMiddlewareCancelledFixture::getAndResetCounter());
    }

    public function testThePostCheckCatchesACancellationRaisedDuringMiddleware(): void
    {
        RouteMatchedMiddlewareCancellingFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture(
            $this->container,
            RouteMatchedMiddlewareCancellingFixture::class
        );

        $result = $handler->routeMatched($this->call, $this->route);

        self::assertInstanceOf(ServiceResponseContract::class, $result);
        self::assertSame(StatusCode::CANCELLED, $result->getStatus()->getCode());
        self::assertSame(1, RouteMatchedMiddlewareCancellingFixture::getAndResetCounter());
    }
}
