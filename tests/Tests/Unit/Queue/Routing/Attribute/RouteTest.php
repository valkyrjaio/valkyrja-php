<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Routing\Attribute;

use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Attribute\Route;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Throwable\Exception\QueueRoutingMissingRouteHandlerException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class RouteTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string DESCRIPTION = 'Send the welcome email';

    public function testDefaultHandlerThrows(): void
    {
        $route = new Route(name: self::NAME, description: self::DESCRIPTION);

        $handler = $route->getHandler();

        // The collector rejects a route that still carries the sentinel, so
        // reaching it means a route was built by hand with no handler. It
        // throws rather than acknowledging, because an acknowledgement
        // destroys the job it was given
        $this->expectException(QueueRoutingMissingRouteHandlerException::class);

        $handler(new Container(), $route);
    }

    public function testTheDefaultHandlerIsNamedSoItCanBeRecognized(): void
    {
        $route = new Route(name: self::NAME, description: self::DESCRIPTION);

        // A closure would compare unequal to itself, so the sentinel is a
        // named reference that a caller can test for
        self::assertSame([Route::class, 'defaultHandler'], $route->getHandler());
    }

    public function testAnExplicitHandlerIsKept(): void
    {
        $route = new Route(
            name: self::NAME,
            description: self::DESCRIPTION,
            handler: static fn (ContainerContract $container, RouteContract $route): JobResult => JobResult::FAIL,
        );

        $handler = $route->getHandler();

        self::assertSame(JobResult::FAIL, $handler(new Container(), $route));
    }
}
