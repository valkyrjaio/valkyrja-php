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

use RuntimeException;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler\ThrowableCaughtHandlerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareFixture;

/**
 * Test the throwableCaught handler.
 */
final class ThrowableCaughtHandlerTest extends HandlerTestCase
{
    public function testWithDefaults(): void
    {
        $handler = new ThrowableCaughtHandlerFixture($this->container);

        self::assertSame($this->response, $handler->throwableCaught($this->call, $this->response, new RuntimeException('boom')));
        self::assertSame(1, $handler->getCount());
    }

    public function testWalksTheChain(): void
    {
        ThrowableCaughtMiddlewareFixture::resetCounter();

        $handler = new ThrowableCaughtHandlerFixture(
            $this->container,
            ThrowableCaughtMiddlewareFixture::class,
            ThrowableCaughtMiddlewareFixture::class
        );

        self::assertSame($this->response, $handler->throwableCaught($this->call, $this->response, new RuntimeException('boom')));
        self::assertSame(3, $handler->getCount());
        self::assertSame(2, ThrowableCaughtMiddlewareFixture::getAndResetCounter());
    }

    public function testAdd(): void
    {
        ThrowableCaughtMiddlewareFixture::resetCounter();

        $handler = new ThrowableCaughtHandlerFixture($this->container);

        $handler->add(ThrowableCaughtMiddlewareFixture::class);

        self::assertSame($this->response, $handler->throwableCaught($this->call, $this->response, new RuntimeException('boom')));
        self::assertSame(1, ThrowableCaughtMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingMiddlewareSkipsTheRemainder(): void
    {
        ThrowableCaughtMiddlewareChangedFixture::resetCounter();
        ThrowableCaughtMiddlewareFixture::resetCounter();

        $handler = new ThrowableCaughtHandlerFixture(
            $this->container,
            ThrowableCaughtMiddlewareChangedFixture::class,
            ThrowableCaughtMiddlewareFixture::class
        );

        $result = $handler->throwableCaught($this->call, $this->response, new RuntimeException('boom'));

        self::assertSame(StatusCode::ABORTED, $result->getStatus()->getCode());
        self::assertSame(1, ThrowableCaughtMiddlewareChangedFixture::getAndResetCounter());
        self::assertSame(0, ThrowableCaughtMiddlewareFixture::getAndResetCounter());
    }

    public function testThePreCheckOverlaysCancellationOnTheResponseInHand(): void
    {
        ThrowableCaughtMiddlewareFixture::resetCounter();

        $this->cancellation->cancel(CancellationReason::DEADLINE_EXCEEDED);

        $handler = new ThrowableCaughtHandlerFixture(
            $this->container,
            ThrowableCaughtMiddlewareFixture::class
        );

        $result = $handler->throwableCaught($this->call, $this->response, new RuntimeException('boom'));

        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $result->getStatus()->getCode());
        self::assertSame(0, ThrowableCaughtMiddlewareFixture::getAndResetCounter());
    }

    public function testThePostCheckPassesAReturnedCancellationThrough(): void
    {
        ThrowableCaughtMiddlewareCancelledFixture::resetCounter();

        $handler = new ThrowableCaughtHandlerFixture(
            $this->container,
            ThrowableCaughtMiddlewareCancelledFixture::class
        );

        $result = $handler->throwableCaught($this->call, $this->response, new RuntimeException('boom'));

        self::assertSame(StatusCode::CANCELLED, $result->getStatus()->getCode());
        self::assertSame(1, ThrowableCaughtMiddlewareCancelledFixture::getAndResetCounter());
    }
}
