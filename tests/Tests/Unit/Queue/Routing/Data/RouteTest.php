<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Routing\Data;

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Data\Route;
use Valkyrja\Queue\Routing\Throwable\Exception\QueueRoutingInvalidRouteNameException;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class RouteTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';
    /** @var non-empty-string */
    protected const string DESCRIPTION = 'Send the welcome email';

    public function testDefaults(): void
    {
        $handler = static fn (): JobResult => JobResult::ACK;

        $route = new Route(
            name: self::NAME,
            description: self::DESCRIPTION,
            handler: $handler
        );

        self::assertSame(self::NAME, $route->getName());
        self::assertSame(self::DESCRIPTION, $route->getDescription());
        self::assertSame($handler, $route->getHandler());
        self::assertEmpty($route->getRouteMatchedMiddleware());
        self::assertEmpty($route->getRouteDispatchedMiddleware());
        self::assertEmpty($route->getThrowableCaughtMiddleware());
        self::assertEmpty($route->getSettlingResultMiddleware());
        self::assertEmpty($route->getResultSettledMiddleware());
    }

    public function testConstructor(): void
    {
        $route = new Route(
            name: self::NAME,
            description: self::DESCRIPTION,
            handler: static fn (): JobResult => JobResult::ACK,
            routeMatchedMiddleware: [RouteMatchedMiddlewareFixture::class],
            routeDispatchedMiddleware: [RouteDispatchedMiddlewareFixture::class],
            throwableCaughtMiddleware: [ThrowableCaughtMiddlewareFixture::class],
            settlingResultMiddleware: [SettlingResultMiddlewareFixture::class],
            resultSettledMiddleware: [ResultSettledMiddlewareFixture::class],
        );

        self::assertSame([RouteMatchedMiddlewareFixture::class], $route->getRouteMatchedMiddleware());
        self::assertSame([RouteDispatchedMiddlewareFixture::class], $route->getRouteDispatchedMiddleware());
        self::assertSame([ThrowableCaughtMiddlewareFixture::class], $route->getThrowableCaughtMiddleware());
        self::assertSame([SettlingResultMiddlewareFixture::class], $route->getSettlingResultMiddleware());
        self::assertSame([ResultSettledMiddlewareFixture::class], $route->getResultSettledMiddleware());
    }

    public function testConstructorRejectsEmptyName(): void
    {
        $this->expectException(QueueRoutingInvalidRouteNameException::class);

        /* @phpstan-ignore-next-line */
        new Route(name: '', description: self::DESCRIPTION, handler: static fn (): JobResult => JobResult::ACK);
    }

    public function testWithNameRejectsEmptyName(): void
    {
        $this->expectException(QueueRoutingInvalidRouteNameException::class);

        /* @phpstan-ignore-next-line */
        $this->route()->withName('');
    }

    public function testWithName(): void
    {
        $route = $this->route();
        $new   = $route->withName('Other');

        self::assertNotSame($route, $new);
        self::assertSame(self::NAME, $route->getName());
        self::assertSame('Other', $new->getName());
    }

    public function testWithDescription(): void
    {
        self::assertSame('Other', $this->route()->withDescription('Other')->getDescription());
    }

    public function testWithHandler(): void
    {
        $handler = static fn (): JobResult => JobResult::FAIL;

        self::assertSame($handler, $this->route()->withHandler($handler)->getHandler());
    }

    public function testWithRouteMatchedMiddleware(): void
    {
        $route = $this->route()->withRouteMatchedMiddleware(RouteMatchedMiddlewareFixture::class);

        self::assertSame([RouteMatchedMiddlewareFixture::class], $route->getRouteMatchedMiddleware());

        $route = $route->withRouteMatchedMiddleware(RouteMatchedMiddlewareChangedFixture::class);

        self::assertSame([RouteMatchedMiddlewareChangedFixture::class], $route->getRouteMatchedMiddleware());
    }

    public function testWithAddedRouteMatchedMiddleware(): void
    {
        $route = $this->route()
            ->withRouteMatchedMiddleware(RouteMatchedMiddlewareFixture::class)
            ->withAddedRouteMatchedMiddleware(RouteMatchedMiddlewareChangedFixture::class);

        self::assertSame(
            [RouteMatchedMiddlewareFixture::class, RouteMatchedMiddlewareChangedFixture::class],
            $route->getRouteMatchedMiddleware()
        );
    }

    public function testWithRouteDispatchedMiddleware(): void
    {
        $route = $this->route()->withRouteDispatchedMiddleware(RouteDispatchedMiddlewareFixture::class);

        self::assertSame([RouteDispatchedMiddlewareFixture::class], $route->getRouteDispatchedMiddleware());

        $route = $route->withRouteDispatchedMiddleware(RouteDispatchedMiddlewareChangedFixture::class);

        self::assertSame([RouteDispatchedMiddlewareChangedFixture::class], $route->getRouteDispatchedMiddleware());
    }

    public function testWithAddedRouteDispatchedMiddleware(): void
    {
        $route = $this->route()
            ->withRouteDispatchedMiddleware(RouteDispatchedMiddlewareFixture::class)
            ->withAddedRouteDispatchedMiddleware(RouteDispatchedMiddlewareChangedFixture::class);

        self::assertSame(
            [RouteDispatchedMiddlewareFixture::class, RouteDispatchedMiddlewareChangedFixture::class],
            $route->getRouteDispatchedMiddleware()
        );
    }

    public function testWithThrowableCaughtMiddleware(): void
    {
        $route = $this->route()->withThrowableCaughtMiddleware(ThrowableCaughtMiddlewareFixture::class);

        self::assertSame([ThrowableCaughtMiddlewareFixture::class], $route->getThrowableCaughtMiddleware());

        $route = $route->withThrowableCaughtMiddleware(ThrowableCaughtMiddlewareChangedFixture::class);

        self::assertSame([ThrowableCaughtMiddlewareChangedFixture::class], $route->getThrowableCaughtMiddleware());
    }

    public function testWithAddedThrowableCaughtMiddleware(): void
    {
        $route = $this->route()
            ->withThrowableCaughtMiddleware(ThrowableCaughtMiddlewareFixture::class)
            ->withAddedThrowableCaughtMiddleware(ThrowableCaughtMiddlewareChangedFixture::class);

        self::assertSame(
            [ThrowableCaughtMiddlewareFixture::class, ThrowableCaughtMiddlewareChangedFixture::class],
            $route->getThrowableCaughtMiddleware()
        );
    }

    public function testWithSettlingResultMiddleware(): void
    {
        $route = $this->route()->withSettlingResultMiddleware(SettlingResultMiddlewareFixture::class);

        self::assertSame([SettlingResultMiddlewareFixture::class], $route->getSettlingResultMiddleware());

        $route = $route->withSettlingResultMiddleware(SettlingResultMiddlewareChangedFixture::class);

        self::assertSame([SettlingResultMiddlewareChangedFixture::class], $route->getSettlingResultMiddleware());
    }

    public function testWithAddedSettlingResultMiddleware(): void
    {
        $route = $this->route()
            ->withSettlingResultMiddleware(SettlingResultMiddlewareFixture::class)
            ->withAddedSettlingResultMiddleware(SettlingResultMiddlewareChangedFixture::class);

        self::assertSame(
            [SettlingResultMiddlewareFixture::class, SettlingResultMiddlewareChangedFixture::class],
            $route->getSettlingResultMiddleware()
        );
    }

    public function testWithResultSettledMiddleware(): void
    {
        $route = $this->route()->withResultSettledMiddleware(ResultSettledMiddlewareFixture::class);

        self::assertSame([ResultSettledMiddlewareFixture::class], $route->getResultSettledMiddleware());

        $route = $route->withResultSettledMiddleware(ResultSettledMiddlewareChangedFixture::class);

        self::assertSame([ResultSettledMiddlewareChangedFixture::class], $route->getResultSettledMiddleware());
    }

    public function testWithAddedResultSettledMiddleware(): void
    {
        $route = $this->route()
            ->withResultSettledMiddleware(ResultSettledMiddlewareFixture::class)
            ->withAddedResultSettledMiddleware(ResultSettledMiddlewareChangedFixture::class);

        self::assertSame(
            [ResultSettledMiddlewareFixture::class, ResultSettledMiddlewareChangedFixture::class],
            $route->getResultSettledMiddleware()
        );
    }

    protected function route(): Route
    {
        return new Route(
            name: self::NAME,
            description: self::DESCRIPTION,
            handler: static fn (): JobResult => JobResult::ACK,
        );
    }
}
