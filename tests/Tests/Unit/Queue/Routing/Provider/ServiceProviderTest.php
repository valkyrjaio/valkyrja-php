<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Routing\Provider;

use Override;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Queue\Middleware\Provider\QueueMiddlewareServiceProvider;
use Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Queue\Routing\Collection\RouteCollection;
use Valkyrja\Queue\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Queue\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Queue\Routing\Data\QueueRoutingData;
use Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Queue\Routing\Dispatcher\Router;
use Valkyrja\Queue\Routing\Provider\QueueRoutingServiceProvider;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;
use Valkyrja\Tests\Fixtures\Application\Provider\QueueRouteProviderFixture;

final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = QueueRoutingServiceProvider::class;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setSingleton(QueueConfigContract::class, new QueueConfig());
        $this->container->setSingleton(ApplicationContract::class, $this->application());
    }

    public function testExpectedPublishers(): void
    {
        $publishers = new QueueRoutingServiceProvider()->publishers();

        self::assertArrayHasKey(RouteCollectorContract::class, $publishers);
        self::assertArrayHasKey(RouterContract::class, $publishers);
        self::assertArrayHasKey(RouteCollectionContract::class, $publishers);
        self::assertArrayHasKey(QueueRoutingData::class, $publishers);
    }

    public function testPublishAttributeRouteCollector(): void
    {
        $this->publish(RouteCollectorContract::class);

        self::assertInstanceOf(AttributeRouteCollector::class, $this->container->getSingleton(RouteCollectorContract::class));
    }

    public function testPublishAttributeRouteCollectorReusesAlreadyPublishedServices(): void
    {
        // Publishing twice must not re-publish the reflector or the attribute
        // collector; branch coverage cannot see this, so it is asserted directly
        $this->publish(RouteCollectorContract::class);

        $reflector = $this->container->getSingleton(ReflectorContract::class);
        $collector = $this->container->getSingleton(CollectorContract::class);

        $this->publish(RouteCollectorContract::class);

        self::assertSame($reflector, $this->container->getSingleton(ReflectorContract::class));
        self::assertSame($collector, $this->container->getSingleton(CollectorContract::class));
    }

    public function testPublishRouter(): void
    {
        $this->publishMiddlewareHandlers();
        $this->publish(RouteCollectorContract::class);
        $this->publish(RouteCollectionContract::class);
        $this->publish(RouterContract::class);

        self::assertInstanceOf(Router::class, $this->container->getSingleton(RouterContract::class));
    }

    public function testPublishRouteCollectionInDebugModeCollectsFromProviders(): void
    {
        $this->publish(RouteCollectorContract::class);
        $this->publish(RouteCollectionContract::class);

        $collection = $this->container->getSingleton(RouteCollectionContract::class);

        self::assertInstanceOf(RouteCollection::class, $collection);
        // The fixture provider contributes the attributed handler class
        self::assertTrue($collection->has('acme.SendWelcomeEmail'));
    }

    public function testPublishRouteCollectionOutsideDebugModeReadsTheCache(): void
    {
        $this->container->setSingleton(ApplicationContract::class, $this->application(debugMode: false));
        $this->container->setSingleton(QueueRoutingData::class, new QueueRoutingData());

        $this->publish(RouteCollectionContract::class);

        self::assertSame([], $this->container->getSingleton(RouteCollectionContract::class)->all());
    }

    public function testPublishDataWithNoControllersOrRoutes(): void
    {
        $this->container->setSingleton(ApplicationContract::class, $this->application(providers: []));
        $this->publish(RouteCollectorContract::class);
        $this->publish(RouteCollectionContract::class);

        self::assertSame([], $this->container->getSingleton(QueueRoutingData::class)->routes);
    }

    /**
     * Publish a single service by its contract.
     *
     * @param class-string $contract
     */
    protected function publish(string $contract): void
    {
        $callback = new QueueRoutingServiceProvider()->publishers()[$contract];

        $callback($this->container);
    }

    /**
     * Publish the stage handlers the router composes.
     */
    protected function publishMiddlewareHandlers(): void
    {
        foreach (new QueueMiddlewareServiceProvider()->publishers() as $callback) {
            $callback($this->container);
        }
    }

    /**
     * @param QueueRouteProviderFixture[] $providers
     */
    protected function application(bool $debugMode = true, array|null $providers = null): ApplicationContract
    {
        $app = self::createStub(ApplicationContract::class);

        $app->method('getDebugMode')->willReturn($debugMode);
        $app->method('getQueueProviders')->willReturn($providers ?? [new QueueRouteProviderFixture()]);

        return $app;
    }
}
