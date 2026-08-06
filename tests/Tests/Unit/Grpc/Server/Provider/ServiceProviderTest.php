<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Server\Provider;

use ReflectionProperty;
use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Kernel\Valkyrja;
use Valkyrja\Grpc\Middleware\Provider\GrpcMiddlewareServiceProvider;
use Valkyrja\Grpc\Routing\Data\GrpcRoutingData;
use Valkyrja\Grpc\Routing\Provider\GrpcRoutingServiceProvider;
use Valkyrja\Grpc\Server\Handler\Contract\ServiceHandlerContract;
use Valkyrja\Grpc\Server\Handler\ServiceHandler;
use Valkyrja\Grpc\Server\Provider\GrpcServerServiceProvider;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Application\Data\GrpcConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = GrpcServerServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(
            ServiceHandlerContract::class,
            new GrpcServerServiceProvider()->publishers()
        );
    }

    public function testPublishServiceHandler(): void
    {
        $this->bootstrap();

        $callback = new GrpcServerServiceProvider()->publishers()[ServiceHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            ServiceHandler::class,
            $this->container->getSingleton(ServiceHandlerContract::class)
        );
    }

    public function testTheHandlerInheritsTheApplicationDebugMode(): void
    {
        $this->bootstrap(debugMode: true);

        GrpcServerServiceProvider::publishServiceHandler($this->container);

        $handler = $this->container->getSingleton(ServiceHandlerContract::class);

        $reflection = new ReflectionProperty($handler, 'debug');

        self::assertTrue($reflection->getValue($handler));
    }

    public function testTheHandlerSharesTheStageHandlerSingletons(): void
    {
        $this->bootstrap();

        GrpcServerServiceProvider::publishServiceHandler($this->container);

        $handler = $this->container->getSingleton(ServiceHandlerContract::class);

        // Per-route SendingResponse and ResponseSent middleware is registered by the Router onto
        // these very instances and consumed later by the kernel, so both must resolve the same
        // singleton or that middleware silently never fires.
        foreach (['sendingResponseHandler', 'responseSentHandler', 'throwableCaughtHandler'] as $property) {
            $onHandler = new ReflectionProperty($handler, $property)->getValue($handler);

            $router   = new ReflectionProperty($handler, 'router')->getValue($handler);
            $onRouter = new ReflectionProperty($router, $property)->getValue($router);

            self::assertSame($onHandler, $onRouter, "The $property is not shared with the router");
        }
    }

    private function bootstrap(bool $debugMode = false): void
    {
        $config = new GrpcConfigFixture(debugMode: $debugMode);

        $this->container->setSingleton(GrpcConfigContract::class, $config);
        $this->container->setSingleton(
            ApplicationContract::class,
            new Valkyrja(container: $this->container, config: $config)
        );

        // Outside debug mode the collection is loaded from the generated cache rather than scanned.
        $this->container->setSingleton(GrpcRoutingData::class, new GrpcRoutingData());

        GrpcMiddlewareServiceProvider::publishCallReceivedHandler($this->container);
        GrpcMiddlewareServiceProvider::publishRouteMatchedHandler($this->container);
        GrpcMiddlewareServiceProvider::publishRouteNotMatchedHandler($this->container);
        GrpcMiddlewareServiceProvider::publishRouteDispatchedHandler($this->container);
        GrpcMiddlewareServiceProvider::publishThrowableCaughtHandler($this->container);
        GrpcMiddlewareServiceProvider::publishSendingResponseHandler($this->container);
        GrpcMiddlewareServiceProvider::publishResponseSentHandler($this->container);

        GrpcRoutingServiceProvider::publishAttributeRouteCollector($this->container);
        GrpcRoutingServiceProvider::publishRouteCollection($this->container);
        GrpcRoutingServiceProvider::publishRouter($this->container);
    }
}
