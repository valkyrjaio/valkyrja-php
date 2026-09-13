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
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler\RouteDispatchedHandlerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareFixture;

/**
 * Test the routeDispatched handler.
 */
final class RouteDispatchedHandlerTest extends HandlerTestCase
{
    public function testWithDefaults(): void
    {
        $handler = new RouteDispatchedHandlerFixture($this->container);

        self::assertSame($this->response, $handler->routeDispatched($this->call, $this->response, $this->route));
        self::assertSame(1, $handler->getCount());
    }

    public function testWalksTheChain(): void
    {
        RouteDispatchedMiddlewareFixture::resetCounter();

        $handler = new RouteDispatchedHandlerFixture(
            $this->container,
            RouteDispatchedMiddlewareFixture::class,
            RouteDispatchedMiddlewareFixture::class
        );

        self::assertSame($this->response, $handler->routeDispatched($this->call, $this->response, $this->route));
        self::assertSame(3, $handler->getCount());
        self::assertSame(2, RouteDispatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testAdd(): void
    {
        RouteDispatchedMiddlewareFixture::resetCounter();

        $handler = new RouteDispatchedHandlerFixture($this->container);

        $handler->add(RouteDispatchedMiddlewareFixture::class);

        self::assertSame($this->response, $handler->routeDispatched($this->call, $this->response, $this->route));
        self::assertSame(1, RouteDispatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingMiddlewareSkipsTheRemainder(): void
    {
        RouteDispatchedMiddlewareChangedFixture::resetCounter();
        RouteDispatchedMiddlewareFixture::resetCounter();

        $handler = new RouteDispatchedHandlerFixture(
            $this->container,
            RouteDispatchedMiddlewareChangedFixture::class,
            RouteDispatchedMiddlewareFixture::class
        );

        $result = $handler->routeDispatched($this->call, $this->response, $this->route);

        self::assertSame(StatusCode::ABORTED, $result->getStatus()->getCode());
        self::assertSame(1, RouteDispatchedMiddlewareChangedFixture::getAndResetCounter());
        self::assertSame(0, RouteDispatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testThePreCheckOverlaysCancellationOnTheResponseInHand(): void
    {
        RouteDispatchedMiddlewareFixture::resetCounter();

        $this->cancellation->cancel(CancellationReason::DEADLINE_EXCEEDED);

        $handler = new RouteDispatchedHandlerFixture(
            $this->container,
            RouteDispatchedMiddlewareFixture::class
        );

        $result = $handler->routeDispatched($this->call, $this->response, $this->route);

        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $result->getStatus()->getCode());
        self::assertSame(0, RouteDispatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testThePostCheckPassesAReturnedCancellationThrough(): void
    {
        RouteDispatchedMiddlewareCancelledFixture::resetCounter();

        $handler = new RouteDispatchedHandlerFixture(
            $this->container,
            RouteDispatchedMiddlewareCancelledFixture::class
        );

        $result = $handler->routeDispatched($this->call, $this->response, $this->route);

        self::assertSame(StatusCode::CANCELLED, $result->getStatus()->getCode());
        self::assertSame(1, RouteDispatchedMiddlewareCancelledFixture::getAndResetCounter());
    }
}
