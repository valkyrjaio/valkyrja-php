<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Routing\Collector;

use Override;
use Valkyrja\Attribute\Collector\Collector;
use Valkyrja\Queue\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Throwable\Exception\QueueRoutingNoRouteHandlerException;
use Valkyrja\Reflection\Reflector\Reflector;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Controller\JobControllerFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Controller\JobControllerInlineHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Controller\JobControllerMissingHandlerFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Controller\UnnamedJobControllerFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function array_filter;
use function array_values;

final class AttributeRouteCollectorTest extends TestCase
{
    protected AttributeRouteCollector $collector;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $reflector = new Reflector();

        $this->collector = new AttributeRouteCollector(
            attributes: new Collector($reflector),
            reflection: $reflector,
        );
    }

    public function testCollectsEveryAttributedRoute(): void
    {
        self::assertCount(2, $this->collector->getRoutes(JobControllerFixture::class));
    }

    public function testRejectsARouteThatNamesNoHandler(): void
    {
        // Without this the route would keep the attribute's sentinel and fail
        // only once a job reached it, long after the author could act on it
        $this->expectException(QueueRoutingNoRouteHandlerException::class);
        $this->expectExceptionMessage(JobControllerMissingHandlerFixture::class . '::noHandler names no handler');

        $this->collector->getRoutes(JobControllerMissingHandlerFixture::class);
    }

    public function testKeepsAHandlerNamedOnTheRouteAttribute(): void
    {
        // The route attribute takes a handler of its own, so a route that
        // names one there needs no Handler attribute
        $routes = $this->collector->getRoutes(JobControllerInlineHandlerFixture::class);
        $inline = array_values(array_filter(
            $routes,
            static fn (RouteContract $route): bool => $route->getName() === 'Inline',
        ));

        self::assertCount(1, $inline);
        self::assertSame([JobControllerInlineHandlerFixture::class, 'handle'], $inline[0]->getHandler());
    }

    public function testTheRouteHandlerAttributeWinsOverTheRouteArgument(): void
    {
        $routes = $this->collector->getRoutes(JobControllerInlineHandlerFixture::class);
        $both   = array_values(array_filter(
            $routes,
            static fn (RouteContract $route): bool => $route->getName() === 'Both',
        ));

        self::assertCount(1, $both);
        self::assertSame([JobControllerInlineHandlerFixture::class, 'viaAttribute'], $both[0]->getHandler());
    }

    public function testCollectsNothingForNoClasses(): void
    {
        self::assertSame([], $this->collector->getRoutes());
    }

    public function testAppliesTheClassNamePrefix(): void
    {
        $route = $this->routeFor('acme.SendWelcomeEmail');

        self::assertSame('Send the welcome email', $route->getDescription());
    }

    public function testKeepsTheRouteNameWithoutAClassName(): void
    {
        // A controller with no class-level Name gets no prefix, so the route
        // name is what the Route attribute declared
        $routes = $this->collector->getRoutes(UnnamedJobControllerFixture::class);

        self::assertCount(1, $routes);
        self::assertSame('RebuildIndex', $routes[0]->getName());
    }

    public function testAppliesTheMethodNameSuffix(): void
    {
        // The class Name prefixes and the method Name suffixes, in that order
        $route = $this->routeFor('acme.ChargeCard.v2');

        self::assertSame('Charge the card', $route->getDescription());
    }

    public function testUsesTheRouteHandlerAttribute(): void
    {
        $route = $this->routeFor('acme.SendWelcomeEmail');

        self::assertSame([JobControllerFixture::class, 'handle'], $route->getHandler());
    }

    public function testDispatchesMiddlewareToItsStage(): void
    {
        $route = $this->routeFor('acme.ChargeCard.v2');

        self::assertSame([RouteMatchedMiddlewareFixture::class], $route->getRouteMatchedMiddleware());
        self::assertSame([RouteDispatchedMiddlewareFixture::class], $route->getRouteDispatchedMiddleware());
        self::assertSame([ThrowableCaughtMiddlewareFixture::class], $route->getThrowableCaughtMiddleware());
        self::assertSame([SettlingResultMiddlewareFixture::class], $route->getSettlingResultMiddleware());
        self::assertSame([ResultSettledMiddlewareFixture::class], $route->getResultSettledMiddleware());
    }

    public function testCollectsNoMiddlewareWhenNoneIsAttributed(): void
    {
        $route = $this->routeFor('acme.SendWelcomeEmail');

        self::assertEmpty($route->getRouteMatchedMiddleware());
        self::assertEmpty($route->getRouteDispatchedMiddleware());
        self::assertEmpty($route->getThrowableCaughtMiddleware());
        self::assertEmpty($route->getSettlingResultMiddleware());
        self::assertEmpty($route->getResultSettledMiddleware());
    }

    /**
     * @param non-empty-string $name
     */
    protected function routeFor(string $name): RouteContract
    {
        foreach ($this->collector->getRoutes(JobControllerFixture::class) as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }

        self::fail("No route named `$name` was collected");
    }
}
