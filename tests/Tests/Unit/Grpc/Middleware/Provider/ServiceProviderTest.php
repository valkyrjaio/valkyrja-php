<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Middleware\Provider;

use ReflectionProperty;
use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Grpc\Middleware\Handler\CallReceivedHandler;
use Valkyrja\Grpc\Middleware\Handler\Contract\CallReceivedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\ResponseSentHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\SendingResponseHandler;
use Valkyrja\Grpc\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Grpc\Middleware\Provider\GrpcMiddlewareServiceProvider;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Application\Data\GrpcConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = GrpcMiddlewareServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(CallReceivedHandlerContract::class, new GrpcMiddlewareServiceProvider()->publishers());
        self::assertArrayHasKey(RouteMatchedHandlerContract::class, new GrpcMiddlewareServiceProvider()->publishers());
        self::assertArrayHasKey(RouteNotMatchedHandlerContract::class, new GrpcMiddlewareServiceProvider()->publishers());
        self::assertArrayHasKey(RouteDispatchedHandlerContract::class, new GrpcMiddlewareServiceProvider()->publishers());
        self::assertArrayHasKey(ThrowableCaughtHandlerContract::class, new GrpcMiddlewareServiceProvider()->publishers());
        self::assertArrayHasKey(SendingResponseHandlerContract::class, new GrpcMiddlewareServiceProvider()->publishers());
        self::assertArrayHasKey(ResponseSentHandlerContract::class, new GrpcMiddlewareServiceProvider()->publishers());
    }

    public function testPublishCallReceivedHandler(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture());

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[CallReceivedHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            CallReceivedHandler::class,
            $this->container->getSingleton(CallReceivedHandlerContract::class)
        );
    }

    public function testPublishCallReceivedHandlerWithCustomConfig(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture(callReceivedMiddleware: ['test']));

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[CallReceivedHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            CallReceivedHandler::class,
            $handler = $this->container->getSingleton(CallReceivedHandlerContract::class)
        );

        $reflection = new ReflectionProperty($handler, 'middleware');

        self::assertSame(['test'], $reflection->getValue($handler));
    }

    public function testPublishRouteMatchedHandler(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture());

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[RouteMatchedHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            RouteMatchedHandler::class,
            $this->container->getSingleton(RouteMatchedHandlerContract::class)
        );
    }

    public function testPublishRouteMatchedHandlerWithCustomConfig(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture(routeMatchedMiddleware: ['test']));

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[RouteMatchedHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            RouteMatchedHandler::class,
            $handler = $this->container->getSingleton(RouteMatchedHandlerContract::class)
        );

        $reflection = new ReflectionProperty($handler, 'middleware');

        self::assertSame(['test'], $reflection->getValue($handler));
    }

    public function testPublishRouteNotMatchedHandler(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture());

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[RouteNotMatchedHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            RouteNotMatchedHandler::class,
            $this->container->getSingleton(RouteNotMatchedHandlerContract::class)
        );
    }

    public function testPublishRouteNotMatchedHandlerWithCustomConfig(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture(routeNotMatchedMiddleware: ['test']));

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[RouteNotMatchedHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            RouteNotMatchedHandler::class,
            $handler = $this->container->getSingleton(RouteNotMatchedHandlerContract::class)
        );

        $reflection = new ReflectionProperty($handler, 'middleware');

        self::assertSame(['test'], $reflection->getValue($handler));
    }

    public function testPublishRouteDispatchedHandler(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture());

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[RouteDispatchedHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            RouteDispatchedHandler::class,
            $this->container->getSingleton(RouteDispatchedHandlerContract::class)
        );
    }

    public function testPublishRouteDispatchedHandlerWithCustomConfig(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture(routeDispatchedMiddleware: ['test']));

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[RouteDispatchedHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            RouteDispatchedHandler::class,
            $handler = $this->container->getSingleton(RouteDispatchedHandlerContract::class)
        );

        $reflection = new ReflectionProperty($handler, 'middleware');

        self::assertSame(['test'], $reflection->getValue($handler));
    }

    public function testPublishThrowableCaughtHandler(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture());

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[ThrowableCaughtHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            ThrowableCaughtHandler::class,
            $this->container->getSingleton(ThrowableCaughtHandlerContract::class)
        );
    }

    public function testPublishThrowableCaughtHandlerWithCustomConfig(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture(throwableCaughtMiddleware: ['test']));

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[ThrowableCaughtHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            ThrowableCaughtHandler::class,
            $handler = $this->container->getSingleton(ThrowableCaughtHandlerContract::class)
        );

        $reflection = new ReflectionProperty($handler, 'middleware');

        self::assertSame(['test'], $reflection->getValue($handler));
    }

    public function testPublishSendingResponseHandler(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture());

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[SendingResponseHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            SendingResponseHandler::class,
            $this->container->getSingleton(SendingResponseHandlerContract::class)
        );
    }

    public function testPublishSendingResponseHandlerWithCustomConfig(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture(sendingResponseMiddleware: ['test']));

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[SendingResponseHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            SendingResponseHandler::class,
            $handler = $this->container->getSingleton(SendingResponseHandlerContract::class)
        );

        $reflection = new ReflectionProperty($handler, 'middleware');

        self::assertSame(['test'], $reflection->getValue($handler));
    }

    public function testPublishResponseSentHandler(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture());

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[ResponseSentHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            ResponseSentHandler::class,
            $this->container->getSingleton(ResponseSentHandlerContract::class)
        );
    }

    public function testPublishResponseSentHandlerWithCustomConfig(): void
    {
        $this->container->setSingleton(GrpcConfigContract::class, new GrpcConfigFixture(responseSentMiddleware: ['test']));

        $callback = new GrpcMiddlewareServiceProvider()->publishers()[ResponseSentHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(
            ResponseSentHandler::class,
            $handler = $this->container->getSingleton(ResponseSentHandlerContract::class)
        );

        $reflection = new ReflectionProperty($handler, 'middleware');

        self::assertSame(['test'], $reflection->getValue($handler));
    }
}
