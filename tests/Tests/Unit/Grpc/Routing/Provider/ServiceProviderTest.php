<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Provider;

use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Kernel\Valkyrja;
use Valkyrja\Grpc\Middleware\Provider\GrpcMiddlewareServiceProvider;
use Valkyrja\Grpc\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Grpc\Routing\Collection\RouteCollection;
use Valkyrja\Grpc\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Grpc\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Grpc\Routing\Data\GrpcRoutingData;
use Valkyrja\Grpc\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Grpc\Routing\Dispatcher\Router;
use Valkyrja\Grpc\Routing\Provider\GrpcRoutingServiceProvider;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Application\Data\GrpcConfigFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\GrpcComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\GrpcRouteProviderWithRoutesFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = GrpcRoutingServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        $publishers = new GrpcRoutingServiceProvider()->publishers();

        self::assertArrayHasKey(RouteCollectorContract::class, $publishers);
        self::assertArrayHasKey(RouterContract::class, $publishers);
        self::assertArrayHasKey(RouteCollectionContract::class, $publishers);
        self::assertArrayHasKey(GrpcRoutingData::class, $publishers);
    }

    public function testPublishAttributeRouteCollector(): void
    {
        $callback = new GrpcRoutingServiceProvider()->publishers()[RouteCollectorContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            AttributeRouteCollector::class,
            $this->container->getSingleton(RouteCollectorContract::class)
        );
    }

    public function testPublishRouter(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture());
        $this->container->setSingleton(ApplicationContract::class, $this->application(debugMode: true));

        GrpcMiddlewareServiceProvider::publishCallReceivedHandler($this->container);
        GrpcMiddlewareServiceProvider::publishRouteMatchedHandler($this->container);
        GrpcMiddlewareServiceProvider::publishRouteNotMatchedHandler($this->container);
        GrpcMiddlewareServiceProvider::publishRouteDispatchedHandler($this->container);
        GrpcMiddlewareServiceProvider::publishThrowableCaughtHandler($this->container);
        GrpcMiddlewareServiceProvider::publishSendingResponseHandler($this->container);
        GrpcMiddlewareServiceProvider::publishResponseSentHandler($this->container);

        GrpcRoutingServiceProvider::publishAttributeRouteCollector($this->container);
        GrpcRoutingServiceProvider::publishRouteCollection($this->container);

        $callback = new GrpcRoutingServiceProvider()->publishers()[RouterContract::class];
        $callback($this->container);

        self::assertInstanceOf(Router::class, $this->container->getSingleton(RouterContract::class));
    }

    public function testPublishRouteCollectionScansInDebugMode(): void
    {
        $this->container->setSingleton(ApplicationContract::class, $this->application(debugMode: true));

        GrpcRoutingServiceProvider::publishAttributeRouteCollector($this->container);

        $callback = new GrpcRoutingServiceProvider()->publishers()[RouteCollectionContract::class];
        $callback($this->container);

        $collection = $this->container->getSingleton(RouteCollectionContract::class);

        self::assertInstanceOf(RouteCollection::class, $collection);
        // The attributed controller and the pre-built route both land in the map.
        self::assertTrue($collection->has('/pkg.Greeter/SayHello'));
        self::assertTrue($collection->has(GrpcRouteProviderWithRoutesFixture::METHOD));
    }

    public function testPublishRouteCollectionLoadsTheCacheOutsideDebugMode(): void
    {
        $this->container->setSingleton(ApplicationContract::class, $this->application());

        $cached = new RouteCollection();
        $cached->add(...new GrpcRouteProviderWithRoutesFixture()->getRoutes());

        $this->container->setSingleton(GrpcRoutingData::class, $cached->getData());

        $callback = new GrpcRoutingServiceProvider()->publishers()[RouteCollectionContract::class];
        $callback($this->container);

        $collection = $this->container->getSingleton(RouteCollectionContract::class);

        self::assertTrue($collection->has(GrpcRouteProviderWithRoutesFixture::METHOD));
        // No scan happened, so the attributed controller is absent.
        self::assertFalse($collection->has('/pkg.Greeter/SayHello'));
    }

    public function testPublishData(): void
    {
        $this->container->setSingleton(ApplicationContract::class, $this->application(debugMode: true));

        GrpcRoutingServiceProvider::publishAttributeRouteCollector($this->container);

        $this->container->setSingleton(RouteCollectionContract::class, new RouteCollection());

        $callback = new GrpcRoutingServiceProvider()->publishers()[GrpcRoutingData::class];
        $callback($this->container);

        $data = $this->container->getSingleton(GrpcRoutingData::class);

        self::assertArrayHasKey('/pkg.Greeter/SayHello', $data->routes);
        self::assertArrayHasKey(GrpcRouteProviderWithRoutesFixture::METHOD, $data->routes);
    }

    public function testPublishDataWithNoProviders(): void
    {
        $this->container->setSingleton(ApplicationContract::class, $this->application(debugMode: true, withGrpc: false));

        $this->container->setSingleton(RouteCollectionContract::class, new RouteCollection());

        GrpcRoutingServiceProvider::publishData($this->container);

        self::assertSame([], $this->container->getSingleton(GrpcRoutingData::class)->routes);
    }

    private function application(bool $debugMode = false, bool $withGrpc = true): ApplicationContract
    {
        return new Valkyrja(
            container: $this->container,
            config: new GrpcConfigFixture(
                debugMode: $debugMode,
                providers: $withGrpc
                    ? [new GrpcComponentProviderFixture()]
                    : [],
            ),
        );
    }
}
