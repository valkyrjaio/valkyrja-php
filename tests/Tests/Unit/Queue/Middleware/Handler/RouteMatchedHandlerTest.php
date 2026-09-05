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
use Valkyrja\Tests\Fixtures\Queue\Middleware\Handler\RouteMatchedHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareFixture;

final class RouteMatchedHandlerTest extends HandlerTestCase
{
    /**
     * Test with no middleware registered.
     */
    public function testWithDefaults(): void
    {
        $handler = new RouteMatchedHandlerFixture($this->container);

        $result = $handler->routeMatched($this->job, $this->route);

        self::assertSame($this->route, $result);
        self::assertSame(1, $handler->getCount());
    }

    /**
     * Test that a middleware which never calls the handler stops the chain.
     */
    public function testAddWithDefault(): void
    {
        RouteMatchedMiddlewareChangedFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture($this->container);

        $handler->add(RouteMatchedMiddlewareChangedFixture::class);
        $result = $handler->routeMatched($this->job, $this->route);

        // Only once, because the middleware never calls back into the handler
        self::assertSame(1, $handler->getCount());
        self::assertSame(1, RouteMatchedMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::FAIL, $result);
    }

    /**
     * Test that middleware run in registration order and the chain terminates.
     */
    public function testAdd(): void
    {
        RouteMatchedMiddlewareChangedFixture::resetCounter();
        RouteMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture(
            $this->container,
            RouteMatchedMiddlewareFixture::class
        );

        $handler->add(RouteMatchedMiddlewareChangedFixture::class);
        $result = $handler->routeMatched($this->job, $this->route);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, RouteMatchedMiddlewareFixture::getCounter());
        self::assertSame(1, RouteMatchedMiddlewareChangedFixture::getCounter());
        self::assertSame(JobResult::FAIL, $result);
    }

    /**
     * Test that a pass-through middleware reaches the end of the chain.
     */
    public function testPassThrough(): void
    {
        RouteMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture(
            $this->container,
            RouteMatchedMiddlewareFixture::class
        );

        $result = $handler->routeMatched($this->job, $this->route);

        self::assertSame(2, $handler->getCount());
        self::assertSame(1, RouteMatchedMiddlewareFixture::getCounter());
        self::assertSame($this->route, $result);
    }

    /**
     * Test that a duplicate registration runs the middleware twice.
     */
    public function testDuplicateMiddlewareRunsTwice(): void
    {
        RouteMatchedMiddlewareFixture::resetCounter();

        $handler = new RouteMatchedHandlerFixture($this->container);

        // Middleware is appended, never deduplicated
        $handler->add(RouteMatchedMiddlewareFixture::class, RouteMatchedMiddlewareFixture::class);
        $result = $handler->routeMatched($this->job, $this->route);

        self::assertSame(2, RouteMatchedMiddlewareFixture::getCounter());
        self::assertSame($this->route, $result);
    }
}
