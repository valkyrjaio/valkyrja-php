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
use Valkyrja\Tests\Fixtures\Queue\Middleware\Handler\JobReceivedHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\JobReceivedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\JobReceivedMiddlewareFixture;

final class JobReceivedHandlerTest extends HandlerTestCase
{
    /**
     * Test with no middleware registered.
     */
    public function testWithDefaults(): void
    {
        $handler = new JobReceivedHandlerFixture($this->container);

        $result = $handler->jobReceived($this->job);

        self::assertSame($this->job, $result);
        self::assertSame(1, $handler->getCount());
    }

    /**
     * Test that a middleware which never calls the handler stops the chain.
     */
    public function testAddWithDefault(): void
    {
        JobReceivedMiddlewareChangedFixture::resetCounter();

        $handler = new JobReceivedHandlerFixture($this->container);

        $handler->add(JobReceivedMiddlewareChangedFixture::class);
        $result = $handler->jobReceived($this->job);

        // Only once, because the middleware never calls back into the handler
        self::assertSame(1, $handler->getCount());
        self::assertSame(1, JobReceivedMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::FAIL, $result);
    }

    /**
     * Test that middleware run in registration order and the chain terminates.
     */
    public function testAdd(): void
    {
        JobReceivedMiddlewareChangedFixture::resetCounter();
        JobReceivedMiddlewareFixture::resetCounter();

        $handler = new JobReceivedHandlerFixture(
            $this->container,
            JobReceivedMiddlewareFixture::class
        );

        $handler->add(JobReceivedMiddlewareChangedFixture::class);
        $result = $handler->jobReceived($this->job);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, JobReceivedMiddlewareFixture::getCounter());
        self::assertSame(1, JobReceivedMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::FAIL, $result);
    }

    /**
     * Test that a pass-through middleware reaches the end of the chain.
     */
    public function testPassThrough(): void
    {
        JobReceivedMiddlewareFixture::resetCounter();

        $handler = new JobReceivedHandlerFixture(
            $this->container,
            JobReceivedMiddlewareFixture::class
        );

        $result = $handler->jobReceived($this->job);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, JobReceivedMiddlewareFixture::getCounter());
        self::assertSame($this->job, $result);
    }

    /**
     * Test that a duplicate registration runs the middleware twice.
     */
    public function testDuplicateMiddlewareRunsTwice(): void
    {
        JobReceivedMiddlewareFixture::resetCounter();

        $handler = new JobReceivedHandlerFixture($this->container);

        // Middleware is appended, never deduplicated
        $handler->add(JobReceivedMiddlewareFixture::class, JobReceivedMiddlewareFixture::class);
        $result = $handler->jobReceived($this->job);

        self::assertSame(2, JobReceivedMiddlewareFixture::getCounter());
        self::assertSame($this->job, $result);
    }
}
