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
use Valkyrja\Tests\Fixtures\Queue\Middleware\Handler\RouteDispatchedHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareFixture;

final class RouteDispatchedHandlerTest extends HandlerTestCase
{
    /**
     * Test with no middleware registered.
     */
    public function testWithDefaults(): void
    {
        $handler = new RouteDispatchedHandlerFixture($this->container);

        $result = $handler->routeDispatched($this->job, JobResult::ACK, $this->route);

        self::assertSame(JobResult::ACK, $result);
        self::assertSame(1, $handler->getCount());
    }

    /**
     * Test that a middleware which never calls the handler stops the chain.
     */
    public function testAddWithDefault(): void
    {
        RouteDispatchedMiddlewareChangedFixture::resetCounter();

        $handler = new RouteDispatchedHandlerFixture($this->container);

        $handler->add(RouteDispatchedMiddlewareChangedFixture::class);
        $result = $handler->routeDispatched($this->job, JobResult::ACK, $this->route);

        // Only once, because the middleware never calls back into the handler
        self::assertSame(1, $handler->getCount());
        self::assertSame(1, RouteDispatchedMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::DEAD_LETTER, $result);
    }

    /**
     * Test that middleware run in registration order and the chain terminates.
     */
    public function testAdd(): void
    {
        RouteDispatchedMiddlewareChangedFixture::resetCounter();
        RouteDispatchedMiddlewareFixture::resetCounter();

        $handler = new RouteDispatchedHandlerFixture(
            $this->container,
            RouteDispatchedMiddlewareFixture::class
        );

        $handler->add(RouteDispatchedMiddlewareChangedFixture::class);
        $result = $handler->routeDispatched($this->job, JobResult::ACK, $this->route);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, RouteDispatchedMiddlewareFixture::getCounter());
        self::assertSame(1, RouteDispatchedMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::DEAD_LETTER, $result);
    }

    /**
     * Test that a pass-through middleware reaches the end of the chain.
     */
    public function testPassThrough(): void
    {
        RouteDispatchedMiddlewareFixture::resetCounter();

        $handler = new RouteDispatchedHandlerFixture(
            $this->container,
            RouteDispatchedMiddlewareFixture::class
        );

        $result = $handler->routeDispatched($this->job, JobResult::ACK, $this->route);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, RouteDispatchedMiddlewareFixture::getCounter());
        self::assertSame(JobResult::ACK, $result);
    }

    /**
     * Test that a duplicate registration runs the middleware twice.
     */
    public function testDuplicateMiddlewareRunsTwice(): void
    {
        RouteDispatchedMiddlewareFixture::resetCounter();

        $handler = new RouteDispatchedHandlerFixture($this->container);

        // Middleware is appended, never deduplicated
        $handler->add(RouteDispatchedMiddlewareFixture::class, RouteDispatchedMiddlewareFixture::class);
        $result = $handler->routeDispatched($this->job, JobResult::ACK, $this->route);

        self::assertSame(2, RouteDispatchedMiddlewareFixture::getCounter());
        self::assertSame(JobResult::ACK, $result);
    }
}
