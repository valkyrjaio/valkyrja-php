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

use RuntimeException;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Tests\Fixtures\Queue\Middleware\Handler\ThrowableCaughtHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareFixture;

final class ThrowableCaughtHandlerTest extends HandlerTestCase
{
    /**
     * Test with no middleware registered.
     */
    public function testWithDefaults(): void
    {
        $handler = new ThrowableCaughtHandlerFixture($this->container);

        $result = $handler->throwableCaught($this->job, JobResult::RETRY, new RuntimeException('test'));

        self::assertSame(JobResult::RETRY, $result);
        self::assertSame(1, $handler->getCount());
    }

    /**
     * Test that a middleware which never calls the handler stops the chain.
     */
    public function testAddWithDefault(): void
    {
        ThrowableCaughtMiddlewareChangedFixture::resetCounter();

        $handler = new ThrowableCaughtHandlerFixture($this->container);

        $handler->add(ThrowableCaughtMiddlewareChangedFixture::class);
        $result = $handler->throwableCaught($this->job, JobResult::RETRY, new RuntimeException('test'));

        // Only once, because the middleware never calls back into the handler
        self::assertSame(1, $handler->getCount());
        self::assertSame(1, ThrowableCaughtMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::DEAD_LETTER, $result);
    }

    /**
     * Test that middleware run in registration order and the chain terminates.
     */
    public function testAdd(): void
    {
        ThrowableCaughtMiddlewareChangedFixture::resetCounter();
        ThrowableCaughtMiddlewareFixture::resetCounter();

        $handler = new ThrowableCaughtHandlerFixture(
            $this->container,
            ThrowableCaughtMiddlewareFixture::class
        );

        $handler->add(ThrowableCaughtMiddlewareChangedFixture::class);
        $result = $handler->throwableCaught($this->job, JobResult::RETRY, new RuntimeException('test'));

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, ThrowableCaughtMiddlewareFixture::getCounter());
        self::assertSame(1, ThrowableCaughtMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::DEAD_LETTER, $result);
    }

    /**
     * Test that a pass-through middleware reaches the end of the chain.
     */
    public function testPassThrough(): void
    {
        ThrowableCaughtMiddlewareFixture::resetCounter();

        $handler = new ThrowableCaughtHandlerFixture(
            $this->container,
            ThrowableCaughtMiddlewareFixture::class
        );

        $result = $handler->throwableCaught($this->job, JobResult::RETRY, new RuntimeException('test'));

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, ThrowableCaughtMiddlewareFixture::getCounter());
        self::assertSame(JobResult::RETRY, $result);
    }

    /**
     * Test that a duplicate registration runs the middleware twice.
     */
    public function testDuplicateMiddlewareRunsTwice(): void
    {
        ThrowableCaughtMiddlewareFixture::resetCounter();

        $handler = new ThrowableCaughtHandlerFixture($this->container);

        // Middleware is appended, never deduplicated
        $handler->add(ThrowableCaughtMiddlewareFixture::class, ThrowableCaughtMiddlewareFixture::class);
        $result = $handler->throwableCaught($this->job, JobResult::RETRY, new RuntimeException('test'));

        self::assertSame(2, ThrowableCaughtMiddlewareFixture::getCounter());
        self::assertSame(JobResult::RETRY, $result);
    }
}
