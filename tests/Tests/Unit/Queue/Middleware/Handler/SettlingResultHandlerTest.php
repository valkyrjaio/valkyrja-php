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
use Valkyrja\Tests\Fixtures\Queue\Middleware\Handler\SettlingResultHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareFixture;

final class SettlingResultHandlerTest extends HandlerTestCase
{
    /**
     * Test with no middleware registered.
     */
    public function testWithDefaults(): void
    {
        $handler = new SettlingResultHandlerFixture($this->container);

        $result = $handler->settlingResult($this->job, JobResult::ACK);

        self::assertSame(JobResult::ACK, $result);
        self::assertSame(1, $handler->getCount());
    }

    /**
     * Test that a middleware which never calls the handler stops the chain.
     */
    public function testAddWithDefault(): void
    {
        SettlingResultMiddlewareChangedFixture::resetCounter();

        $handler = new SettlingResultHandlerFixture($this->container);

        $handler->add(SettlingResultMiddlewareChangedFixture::class);
        $result = $handler->settlingResult($this->job, JobResult::ACK);

        // Only once, because the middleware never calls back into the handler
        self::assertSame(1, $handler->getCount());
        self::assertSame(1, SettlingResultMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::DEAD_LETTER, $result);
    }

    /**
     * Test that middleware run in registration order and the chain terminates.
     */
    public function testAdd(): void
    {
        SettlingResultMiddlewareChangedFixture::resetCounter();
        SettlingResultMiddlewareFixture::resetCounter();

        $handler = new SettlingResultHandlerFixture(
            $this->container,
            SettlingResultMiddlewareFixture::class
        );

        $handler->add(SettlingResultMiddlewareChangedFixture::class);
        $result = $handler->settlingResult($this->job, JobResult::ACK);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, SettlingResultMiddlewareFixture::getCounter());
        self::assertSame(1, SettlingResultMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::DEAD_LETTER, $result);
    }

    /**
     * Test that a pass-through middleware reaches the end of the chain.
     */
    public function testPassThrough(): void
    {
        SettlingResultMiddlewareFixture::resetCounter();

        $handler = new SettlingResultHandlerFixture(
            $this->container,
            SettlingResultMiddlewareFixture::class
        );

        $result = $handler->settlingResult($this->job, JobResult::ACK);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, SettlingResultMiddlewareFixture::getCounter());
        self::assertSame(JobResult::ACK, $result);
    }

    /**
     * Test that a duplicate registration runs the middleware twice.
     */
    public function testDuplicateMiddlewareRunsTwice(): void
    {
        SettlingResultMiddlewareFixture::resetCounter();

        $handler = new SettlingResultHandlerFixture($this->container);

        // Middleware is appended, never deduplicated
        $handler->add(SettlingResultMiddlewareFixture::class, SettlingResultMiddlewareFixture::class);
        $result = $handler->settlingResult($this->job, JobResult::ACK);

        self::assertSame(2, SettlingResultMiddlewareFixture::getCounter());
        self::assertSame(JobResult::ACK, $result);
    }
}
