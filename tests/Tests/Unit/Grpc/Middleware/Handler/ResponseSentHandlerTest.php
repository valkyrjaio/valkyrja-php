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
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler\ResponseSentHandlerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareStoppedFixture;

/**
 * Test the response sent handler.
 */
final class ResponseSentHandlerTest extends HandlerTestCase
{
    public function testWithDefaults(): void
    {
        $handler = new ResponseSentHandlerFixture($this->container);

        $handler->responseSent($this->call, $this->response);

        self::assertSame(1, $handler->getCount());
    }

    public function testWalksTheChain(): void
    {
        ResponseSentMiddlewareFixture::resetCounter();

        $handler = new ResponseSentHandlerFixture(
            $this->container,
            ResponseSentMiddlewareFixture::class,
            ResponseSentMiddlewareFixture::class
        );

        $handler->responseSent($this->call, $this->response);

        self::assertSame(3, $handler->getCount());
        self::assertSame(2, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testAdd(): void
    {
        ResponseSentMiddlewareFixture::resetCounter();

        $handler = new ResponseSentHandlerFixture($this->container);

        $handler->add(ResponseSentMiddlewareFixture::class);
        $handler->responseSent($this->call, $this->response);

        self::assertSame(1, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingMiddlewareSkipsTheRemainder(): void
    {
        ResponseSentMiddlewareStoppedFixture::resetCounter();
        ResponseSentMiddlewareFixture::resetCounter();

        $handler = new ResponseSentHandlerFixture(
            $this->container,
            ResponseSentMiddlewareStoppedFixture::class,
            ResponseSentMiddlewareFixture::class
        );

        $handler->responseSent($this->call, $this->response);

        self::assertSame(1, ResponseSentMiddlewareStoppedFixture::getAndResetCounter());
        self::assertSame(0, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testTheStageStillRunsForACancelledCall(): void
    {
        ResponseSentMiddlewareFixture::resetCounter();

        $this->cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $handler = new ResponseSentHandlerFixture(
            $this->container,
            ResponseSentMiddlewareFixture::class
        );

        $handler->responseSent($this->call, $this->response);

        // Observability of a cancelled call matters more than of a successful one, so this stage
        // applies no cancellation short-circuit.
        self::assertSame(1, ResponseSentMiddlewareFixture::getAndResetCounter());
    }
}
