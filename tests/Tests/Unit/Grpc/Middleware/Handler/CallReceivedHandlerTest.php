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
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareCancellingFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler\CallReceivedHandlerFixture;

/**
 * Test the call received handler.
 */
final class CallReceivedHandlerTest extends HandlerTestCase
{
    public function testWithDefaults(): void
    {
        $handler = new CallReceivedHandlerFixture($this->container);

        self::assertSame($this->call, $handler->callReceived($this->call));
        self::assertSame(1, $handler->getCount());
    }

    public function testConstructsItsOwnContainerByDefault(): void
    {
        $handler = new CallReceivedHandlerFixture();

        self::assertSame($this->call, $handler->callReceived($this->call));
        self::assertSame(1, $handler->getCount());
    }

    public function testWalksTheChain(): void
    {
        CallReceivedMiddlewareFixture::resetCounter();

        $handler = new CallReceivedHandlerFixture(
            $this->container,
            CallReceivedMiddlewareFixture::class,
            CallReceivedMiddlewareFixture::class
        );

        self::assertSame($this->call, $handler->callReceived($this->call));
        // Once per middleware, plus the final pass that finds no next middleware.
        self::assertSame(3, $handler->getCount());
        self::assertSame(2, CallReceivedMiddlewareFixture::getAndResetCounter());
    }

    public function testAdd(): void
    {
        CallReceivedMiddlewareFixture::resetCounter();

        $handler = new CallReceivedHandlerFixture($this->container);

        $handler->add(CallReceivedMiddlewareFixture::class);

        self::assertSame($this->call, $handler->callReceived($this->call));
        self::assertSame(1, CallReceivedMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingMiddlewareSkipsTheRemainder(): void
    {
        CallReceivedMiddlewareChangedFixture::resetCounter();
        CallReceivedMiddlewareFixture::resetCounter();

        $handler = new CallReceivedHandlerFixture(
            $this->container,
            CallReceivedMiddlewareChangedFixture::class,
            CallReceivedMiddlewareFixture::class
        );

        $result = $handler->callReceived($this->call);

        self::assertInstanceOf(ServiceResponseContract::class, $result);
        self::assertSame(StatusCode::ABORTED, $result->getStatus()->getCode());
        self::assertSame(1, CallReceivedMiddlewareChangedFixture::getAndResetCounter());
        self::assertSame(0, CallReceivedMiddlewareFixture::getAndResetCounter());
    }

    public function testTheEntryPreCheckFastExitsAnAlreadyCancelledCall(): void
    {
        CallReceivedMiddlewareFixture::resetCounter();

        $this->cancellation->cancel(CancellationReason::DEADLINE_EXCEEDED);

        $handler = new CallReceivedHandlerFixture(
            $this->container,
            CallReceivedMiddlewareFixture::class
        );

        $result = $handler->callReceived($this->call);

        self::assertInstanceOf(ServiceResponseContract::class, $result);
        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $result->getStatus()->getCode());
        // The pre-check fast-exits before any middleware runs.
        self::assertSame(0, CallReceivedMiddlewareFixture::getAndResetCounter());
    }

    public function testThePostCheckPassesAReturnedCancellationThrough(): void
    {
        CallReceivedMiddlewareCancelledFixture::resetCounter();

        $handler = new CallReceivedHandlerFixture(
            $this->container,
            CallReceivedMiddlewareCancelledFixture::class
        );

        $result = $handler->callReceived($this->call);

        self::assertInstanceOf(ServiceResponseContract::class, $result);
        self::assertSame(StatusCode::CANCELLED, $result->getStatus()->getCode());
        self::assertSame(1, CallReceivedMiddlewareCancelledFixture::getAndResetCounter());
    }

    public function testThePostCheckCatchesACancellationRaisedDuringMiddleware(): void
    {
        CallReceivedMiddlewareCancellingFixture::resetCounter();

        $handler = new CallReceivedHandlerFixture(
            $this->container,
            CallReceivedMiddlewareCancellingFixture::class
        );

        $result = $handler->callReceived($this->call);

        self::assertInstanceOf(ServiceResponseContract::class, $result);
        self::assertSame(StatusCode::CANCELLED, $result->getStatus()->getCode());
        self::assertSame(1, CallReceivedMiddlewareCancellingFixture::getAndResetCounter());
    }
}
