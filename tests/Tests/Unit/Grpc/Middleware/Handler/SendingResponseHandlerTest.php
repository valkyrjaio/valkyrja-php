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
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler\SendingResponseHandlerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareFixture;

/**
 * Test the sending response handler.
 */
final class SendingResponseHandlerTest extends HandlerTestCase
{
    public function testWithDefaults(): void
    {
        $handler = new SendingResponseHandlerFixture($this->container);

        self::assertSame($this->response, $handler->sendingResponse($this->call, $this->response));
        self::assertSame(1, $handler->getCount());
    }

    public function testWalksTheChain(): void
    {
        SendingResponseMiddlewareFixture::resetCounter();

        $handler = new SendingResponseHandlerFixture(
            $this->container,
            SendingResponseMiddlewareFixture::class,
            SendingResponseMiddlewareFixture::class
        );

        self::assertSame($this->response, $handler->sendingResponse($this->call, $this->response));
        self::assertSame(3, $handler->getCount());
        self::assertSame(2, SendingResponseMiddlewareFixture::getAndResetCounter());
    }

    public function testAdd(): void
    {
        SendingResponseMiddlewareFixture::resetCounter();

        $handler = new SendingResponseHandlerFixture($this->container);

        $handler->add(SendingResponseMiddlewareFixture::class);

        self::assertSame($this->response, $handler->sendingResponse($this->call, $this->response));
        self::assertSame(1, SendingResponseMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingMiddlewareSkipsTheRemainder(): void
    {
        SendingResponseMiddlewareChangedFixture::resetCounter();
        SendingResponseMiddlewareFixture::resetCounter();

        $handler = new SendingResponseHandlerFixture(
            $this->container,
            SendingResponseMiddlewareChangedFixture::class,
            SendingResponseMiddlewareFixture::class
        );

        $result = $handler->sendingResponse($this->call, $this->response);

        self::assertSame(StatusCode::ABORTED, $result->getStatus()->getCode());
        self::assertSame(1, SendingResponseMiddlewareChangedFixture::getAndResetCounter());
        self::assertSame(0, SendingResponseMiddlewareFixture::getAndResetCounter());
    }

    public function testTheStageStillRunsForACancelledCall(): void
    {
        SendingResponseMiddlewareFixture::resetCounter();

        $this->cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $handler = new SendingResponseHandlerFixture(
            $this->container,
            SendingResponseMiddlewareFixture::class
        );

        // Per the fast-exit path this stage always runs, so it applies no cancellation
        // short-circuit and the response passes through untouched.
        self::assertSame($this->response, $handler->sendingResponse($this->call, $this->response));
        self::assertSame(1, SendingResponseMiddlewareFixture::getAndResetCounter());
    }
}
