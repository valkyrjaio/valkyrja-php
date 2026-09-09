<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Middleware\Handler;

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Tests\Fixtures\Queue\Middleware\Handler\ResultSettledHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareFixture;

final class ResultSettledHandlerTest extends HandlerTestCase
{
    /**
     * Test with no middleware registered.
     */
    public function testWithDefaults(): void
    {
        $handler = new ResultSettledHandlerFixture($this->container);

        $handler->resultSettled($this->job, JobResult::ACK);

        self::assertSame(1, $handler->getCount());
    }

    /**
     * Test that a middleware which never calls the handler stops the chain.
     */
    public function testAddWithDefault(): void
    {
        ResultSettledMiddlewareChangedFixture::resetCounter();

        $handler = new ResultSettledHandlerFixture($this->container);

        $handler->add(ResultSettledMiddlewareChangedFixture::class);
        $handler->resultSettled($this->job, JobResult::ACK);

        // Only once, because the middleware never calls back into the handler
        self::assertSame(1, $handler->getCount());
        self::assertSame(1, ResultSettledMiddlewareChangedFixture::getCounter());
    }

    /**
     * Test that middleware run in registration order and the chain terminates.
     */
    public function testAdd(): void
    {
        ResultSettledMiddlewareChangedFixture::resetCounter();
        ResultSettledMiddlewareFixture::resetCounter();

        $handler = new ResultSettledHandlerFixture(
            $this->container,
            ResultSettledMiddlewareFixture::class
        );

        $handler->add(ResultSettledMiddlewareChangedFixture::class);
        $handler->resultSettled($this->job, JobResult::ACK);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, ResultSettledMiddlewareFixture::getCounter());
        self::assertSame(1, ResultSettledMiddlewareChangedFixture::getCounter());
    }

    /**
     * Test that a pass-through middleware reaches the end of the chain.
     */
    public function testPassThrough(): void
    {
        ResultSettledMiddlewareFixture::resetCounter();

        $handler = new ResultSettledHandlerFixture(
            $this->container,
            ResultSettledMiddlewareFixture::class
        );

        $handler->resultSettled($this->job, JobResult::ACK);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, ResultSettledMiddlewareFixture::getCounter());
    }

    /**
     * Test that a duplicate registration runs the middleware twice.
     */
    public function testDuplicateMiddlewareRunsTwice(): void
    {
        ResultSettledMiddlewareFixture::resetCounter();

        $handler = new ResultSettledHandlerFixture($this->container);

        // Middleware is appended, never deduplicated
        $handler->add(ResultSettledMiddlewareFixture::class, ResultSettledMiddlewareFixture::class);
        $handler->resultSettled($this->job, JobResult::ACK);

        self::assertSame(2, ResultSettledMiddlewareFixture::getCounter());
    }
}
