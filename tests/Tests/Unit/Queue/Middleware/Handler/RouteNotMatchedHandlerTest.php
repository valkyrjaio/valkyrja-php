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
use Valkyrja\Tests\Fixtures\Queue\Middleware\Handler\RouteNotMatchedHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteNotMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteNotMatchedMiddlewareFixture;

final class RouteNotMatchedHandlerTest extends HandlerTestCase
{
    /**
     * Test with no middleware registered.
     */
    public function testWithDefaults(): void
    {
        $handler = new RouteNotMatchedHandlerFixture($this->container);

        $result = $handler->routeNotMatched($this->job, JobResult::FAIL);

        self::assertSame(JobResult::FAIL, $result);
        self::assertSame(1, $handler->getCount());
    }

    /**
     * Test that a middleware which never calls the handler stops the chain.
     */
    public function testAddWithDefault(): void
    {
        RouteNotMatchedMiddlewareChangedFixture::resetCounter();

        $handler = new RouteNotMatchedHandlerFixture($this->container);

        $handler->add(RouteNotMatchedMiddlewareChangedFixture::class);
        $result = $handler->routeNotMatched($this->job, JobResult::FAIL);

        // Only once, because the middleware never calls back into the handler
        self::assertSame(1, $handler->getCount());
        self::assertSame(1, RouteNotMatchedMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::DEAD_LETTER, $result);
    }

    /**
     * Test that middleware run in registration order and the chain terminates.
     */
    public function testAdd(): void
    {
        RouteNotMatchedMiddlewareChangedFixture::resetCounter();
        RouteNotMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteNotMatchedHandlerFixture(
            $this->container,
            RouteNotMatchedMiddlewareFixture::class
        );

        $handler->add(RouteNotMatchedMiddlewareChangedFixture::class);
        $result = $handler->routeNotMatched($this->job, JobResult::FAIL);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, RouteNotMatchedMiddlewareFixture::getCounter());
        self::assertSame(1, RouteNotMatchedMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::DEAD_LETTER, $result);
    }

    /**
     * Test that a pass-through middleware reaches the end of the chain.
     */
    public function testPassThrough(): void
    {
        RouteNotMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteNotMatchedHandlerFixture(
            $this->container,
            RouteNotMatchedMiddlewareFixture::class
        );

        $result = $handler->routeNotMatched($this->job, JobResult::FAIL);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, RouteNotMatchedMiddlewareFixture::getCounter());
        self::assertSame(JobResult::FAIL, $result);
    }

    /**
     * Test that a duplicate registration runs the middleware twice.
     */
    public function testDuplicateMiddlewareRunsTwice(): void
    {
        RouteNotMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteNotMatchedHandlerFixture($this->container);

        // Middleware is appended, never deduplicated
        $handler->add(RouteNotMatchedMiddlewareFixture::class, RouteNotMatchedMiddlewareFixture::class);
        $result = $handler->routeNotMatched($this->job, JobResult::FAIL);

        self::assertSame(2, RouteNotMatchedMiddlewareFixture::getCounter());
        self::assertSame(JobResult::FAIL, $result);
    }
}
