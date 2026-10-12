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

use Override;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\JobReceivedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\JobReceivedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteNotMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteNotMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

abstract class HandlerTestCase extends TestCase
{
    protected Container $container;

    protected Job $job;

    protected RouteContract $route;

    /**
     * @inheritDoc
     */
    #[Override]
    protected function setUp(): void
    {
        $this->container = new Container();

        // Every middleware the handler tests schedule is bound, the same way an application binds its own.
        $this->container->bindSingleton(JobReceivedMiddlewareChangedFixture::class, static fn (): JobReceivedMiddlewareChangedFixture => new JobReceivedMiddlewareChangedFixture());
        $this->container->bindSingleton(JobReceivedMiddlewareFixture::class, static fn (): JobReceivedMiddlewareFixture => new JobReceivedMiddlewareFixture());
        $this->container->bindSingleton(ResultSettledMiddlewareChangedFixture::class, static fn (): ResultSettledMiddlewareChangedFixture => new ResultSettledMiddlewareChangedFixture());
        $this->container->bindSingleton(ResultSettledMiddlewareFixture::class, static fn (): ResultSettledMiddlewareFixture => new ResultSettledMiddlewareFixture());
        $this->container->bindSingleton(RouteDispatchedMiddlewareChangedFixture::class, static fn (): RouteDispatchedMiddlewareChangedFixture => new RouteDispatchedMiddlewareChangedFixture());
        $this->container->bindSingleton(RouteDispatchedMiddlewareFixture::class, static fn (): RouteDispatchedMiddlewareFixture => new RouteDispatchedMiddlewareFixture());
        $this->container->bindSingleton(RouteMatchedMiddlewareChangedFixture::class, static fn (): RouteMatchedMiddlewareChangedFixture => new RouteMatchedMiddlewareChangedFixture());
        $this->container->bindSingleton(RouteMatchedMiddlewareFixture::class, static fn (): RouteMatchedMiddlewareFixture => new RouteMatchedMiddlewareFixture());
        $this->container->bindSingleton(RouteNotMatchedMiddlewareChangedFixture::class, static fn (): RouteNotMatchedMiddlewareChangedFixture => new RouteNotMatchedMiddlewareChangedFixture());
        $this->container->bindSingleton(RouteNotMatchedMiddlewareFixture::class, static fn (): RouteNotMatchedMiddlewareFixture => new RouteNotMatchedMiddlewareFixture());
        $this->container->bindSingleton(SettlingResultMiddlewareChangedFixture::class, static fn (): SettlingResultMiddlewareChangedFixture => new SettlingResultMiddlewareChangedFixture());
        $this->container->bindSingleton(SettlingResultMiddlewareFixture::class, static fn (): SettlingResultMiddlewareFixture => new SettlingResultMiddlewareFixture());
        $this->container->bindSingleton(ThrowableCaughtMiddlewareChangedFixture::class, static fn (): ThrowableCaughtMiddlewareChangedFixture => new ThrowableCaughtMiddlewareChangedFixture());
        $this->container->bindSingleton(ThrowableCaughtMiddlewareFixture::class, static fn (): ThrowableCaughtMiddlewareFixture => new ThrowableCaughtMiddlewareFixture());

        $this->job   = new Job(name: 'SendWelcomeEmail');
        $this->route = self::createStub(RouteContract::class);
    }
}
