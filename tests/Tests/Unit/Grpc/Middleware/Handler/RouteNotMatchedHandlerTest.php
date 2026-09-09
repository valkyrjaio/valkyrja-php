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
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler\RouteNotMatchedHandlerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteNotMatchedMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteNotMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteNotMatchedMiddlewareFixture;

/**
 * Test the routeNotMatched handler.
 */
final class RouteNotMatchedHandlerTest extends HandlerTestCase
{
    public function testWithDefaults(): void
    {
        $handler = new RouteNotMatchedHandlerFixture($this->container);

        self::assertSame($this->response, $handler->routeNotMatched($this->call, $this->response));
        self::assertSame(1, $handler->getCount());
    }

    public function testWalksTheChain(): void
    {
        RouteNotMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteNotMatchedHandlerFixture(
            $this->container,
            RouteNotMatchedMiddlewareFixture::class,
            RouteNotMatchedMiddlewareFixture::class
        );

        self::assertSame($this->response, $handler->routeNotMatched($this->call, $this->response));
        self::assertSame(3, $handler->getCount());
        self::assertSame(2, RouteNotMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testAdd(): void
    {
        RouteNotMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteNotMatchedHandlerFixture($this->container);

        $handler->add(RouteNotMatchedMiddlewareFixture::class);

        self::assertSame($this->response, $handler->routeNotMatched($this->call, $this->response));
        self::assertSame(1, RouteNotMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingMiddlewareSkipsTheRemainder(): void
    {
        RouteNotMatchedMiddlewareChangedFixture::resetCounter();
        RouteNotMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteNotMatchedHandlerFixture(
            $this->container,
            RouteNotMatchedMiddlewareChangedFixture::class,
            RouteNotMatchedMiddlewareFixture::class
        );

        $result = $handler->routeNotMatched($this->call, $this->response);

        self::assertSame(StatusCode::ABORTED, $result->getStatus()->getCode());
        self::assertSame(1, RouteNotMatchedMiddlewareChangedFixture::getAndResetCounter());
        self::assertSame(0, RouteNotMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testThePreCheckOverlaysCancellationOnTheResponseInHand(): void
    {
        RouteNotMatchedMiddlewareFixture::resetCounter();

        $this->cancellation->cancel(CancellationReason::DEADLINE_EXCEEDED);

        $handler = new RouteNotMatchedHandlerFixture(
            $this->container,
            RouteNotMatchedMiddlewareFixture::class
        );

        $result = $handler->routeNotMatched($this->call, $this->response);

        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $result->getStatus()->getCode());
        self::assertSame(0, RouteNotMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testThePostCheckPassesAReturnedCancellationThrough(): void
    {
        RouteNotMatchedMiddlewareCancelledFixture::resetCounter();

        $handler = new RouteNotMatchedHandlerFixture(
            $this->container,
            RouteNotMatchedMiddlewareCancelledFixture::class
        );

        $result = $handler->routeNotMatched($this->call, $this->response);

        self::assertSame(StatusCode::CANCELLED, $result->getStatus()->getCode());
        self::assertSame(1, RouteNotMatchedMiddlewareCancelledFixture::getAndResetCounter());
    }
}
