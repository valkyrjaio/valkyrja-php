<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Routing\Dispatcher;

use Override;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Middleware\Handler\ResultSettledHandler;
use Valkyrja\Queue\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Queue\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Queue\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Queue\Middleware\Handler\SettlingResultHandler;
use Valkyrja\Queue\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Queue\Routing\Collection\RouteCollection;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\Route;
use Valkyrja\Queue\Routing\Dispatcher\Router;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class RouterTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    protected Container $container;

    protected RouteCollection $collection;

    protected RouteMatchedHandler $routeMatchedHandler;

    protected RouteNotMatchedHandler $routeNotMatchedHandler;

    protected RouteDispatchedHandler $routeDispatchedHandler;

    protected ThrowableCaughtHandler $throwableCaughtHandler;

    protected SettlingResultHandler $settlingResultHandler;

    protected ResultSettledHandler $resultSettledHandler;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container              = new Container();
        $this->collection             = new RouteCollection();
        $this->routeMatchedHandler    = new RouteMatchedHandler($this->container);
        $this->routeNotMatchedHandler = new RouteNotMatchedHandler($this->container);
        $this->routeDispatchedHandler = new RouteDispatchedHandler($this->container);
        $this->throwableCaughtHandler = new ThrowableCaughtHandler($this->container);
        $this->settlingResultHandler  = new SettlingResultHandler($this->container);
        $this->resultSettledHandler   = new ResultSettledHandler($this->container);
    }

    public function testDispatchAnUnknownJobNameFails(): void
    {
        // An unknown job has no handler to retry into, so the default terminal fails it
        self::assertSame(JobResult::FAIL, $this->router()->dispatch(new Job(name: 'Unknown')));
    }

    public function testDispatchResolvesFromTheMap(): void
    {
        $this->collection->add($this->route(JobResult::ACK));

        self::assertSame(JobResult::ACK, $this->router()->dispatch(new Job(name: self::NAME)));
    }

    public function testDispatchPassesTheHandlerResultThrough(): void
    {
        $this->collection->add($this->route(JobResult::RETRY));

        self::assertSame(JobResult::RETRY, $this->router()->dispatch(new Job(name: self::NAME)));
    }

    public function testDispatchRoutePublishesTheRouteInTheContainer(): void
    {
        $route = $this->route(JobResult::ACK);

        $this->router()->dispatchRoute(new Job(name: self::NAME), $route);

        self::assertSame($route, $this->container->getSingleton(RouteContract::class));
    }

    public function testDispatchRouteRegistersPerRouteMiddleware(): void
    {
        RouteMatchedMiddlewareFixture::resetCounter();
        RouteDispatchedMiddlewareFixture::resetCounter();

        $route = $this->route(JobResult::ACK)
            ->withRouteMatchedMiddleware(RouteMatchedMiddlewareFixture::class)
            ->withRouteDispatchedMiddleware(RouteDispatchedMiddlewareFixture::class);

        $result = $this->router()->dispatchRoute(new Job(name: self::NAME), $route);

        self::assertSame(JobResult::ACK, $result);
        self::assertSame(1, RouteMatchedMiddlewareFixture::getCounter());
        self::assertSame(1, RouteDispatchedMiddlewareFixture::getCounter());
    }

    public function testDispatchRouteRegistersTheSettlementStageMiddleware(): void
    {
        // The router registers onto the shared handlers so the kernel's later
        // settlement stages run this route's middleware
        $route = $this->route(JobResult::ACK)
            ->withThrowableCaughtMiddleware(ThrowableCaughtMiddlewareFixture::class)
            ->withSettlingResultMiddleware(SettlingResultMiddlewareFixture::class)
            ->withResultSettledMiddleware(ResultSettledMiddlewareFixture::class);

        $this->router()->dispatchRoute(new Job(name: self::NAME), $route);

        SettlingResultMiddlewareFixture::resetCounter();
        ResultSettledMiddlewareFixture::resetCounter();

        $this->settlingResultHandler->settlingResult(new Job(name: self::NAME), JobResult::ACK);
        $this->resultSettledHandler->resultSettled(new Job(name: self::NAME), JobResult::ACK);

        self::assertSame(1, SettlingResultMiddlewareFixture::getCounter());
        self::assertSame(1, ResultSettledMiddlewareFixture::getCounter());
    }

    public function testRouteMatchedMiddlewareCanShortCircuit(): void
    {
        RouteMatchedMiddlewareChangedFixture::resetCounter();

        $route = $this->route(JobResult::ACK)
            ->withRouteMatchedMiddleware(RouteMatchedMiddlewareChangedFixture::class);

        // The middleware returns a result, so the handler never runs
        self::assertSame(JobResult::FAIL, $this->router()->dispatchRoute(new Job(name: self::NAME), $route));
        self::assertSame(1, RouteMatchedMiddlewareChangedFixture::getCounter());
    }

    protected function route(JobResult $result): Route
    {
        return new Route(
            name: self::NAME,
            description: 'Send the welcome email',
            handler: static fn (ContainerContract $container, RouteContract $route): JobResult => $result,
        );
    }

    protected function router(): Router
    {
        return new Router(
            container: $this->container,
            collection: $this->collection,
            throwableCaughtHandler: $this->throwableCaughtHandler,
            routeMatchedHandler: $this->routeMatchedHandler,
            routeNotMatchedHandler: $this->routeNotMatchedHandler,
            routeDispatchedHandler: $this->routeDispatchedHandler,
            settlingResultHandler: $this->settlingResultHandler,
            resultSettledHandler: $this->resultSettledHandler,
        );
    }
}
