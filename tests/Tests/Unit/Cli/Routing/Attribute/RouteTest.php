<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cli\Routing\Attribute;

use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Support\NoRouteHandler;
use Valkyrja\Tests\Fixtures\Cli\Routing\Command\CommandFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the CLI route attribute.
 */
final class RouteTest extends TestCase
{
    public function testDefaults(): void
    {
        $route = new Route(name: 'test', description: 'A test command');

        self::assertSame('test', $route->getName());
        self::assertSame('A test command', $route->getDescription());
    }

    public function testTheStandInIsRecognizable(): void
    {
        $route = new Route(name: 'test', description: 'A test command');

        // Each route would build a fresh closure, leaving the collector no
        // shared instance to compare against, so the stand-in is a named type
        self::assertInstanceOf(NoRouteHandler::class, $route->getHandler());
    }

    public function testAnExplicitHandlerIsKept(): void
    {
        $handler = [CommandFixture::class, 'handler'];

        $route = new Route(name: 'test', description: 'A test command', handler: $handler);

        self::assertSame($handler, $route->getHandler());
    }
}
