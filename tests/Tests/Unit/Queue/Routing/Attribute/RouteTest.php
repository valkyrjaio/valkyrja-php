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
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class RouteTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string DESCRIPTION = 'Send the welcome email';

    public function testDefaultHandlerReturnsAck(): void
    {
        $route = new Route(name: self::NAME, description: self::DESCRIPTION);

        $handler = $route->getHandler();

        // A route that names no handler acknowledges, so a job routed to it
        // never retries on the strength of a missing handler alone
        self::assertSame(JobResult::ACK, $handler(new Container(), $route));
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
