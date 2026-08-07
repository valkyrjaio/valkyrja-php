<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Middleware\Handler;

use Valkyrja\Container\Manager\Container;
use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareCancellingFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareStoppedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareCancellingFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteNotMatchedMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteNotMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteNotMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareCancelledFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * The Handler test case.
 */
abstract class HandlerTestCase extends TestCase
{
    protected Container $container;

    protected CancellationToken $cancellation;

    protected ServiceCall $call;

    protected ServiceResponse $response;

    protected RouteContract $route;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->container    = new Container();

        // Every middleware the handler tests schedule is bound, the same way an application binds its own.
        $this->container->bindSingleton(AllMiddlewareFixture::class, static fn (): AllMiddlewareFixture => new AllMiddlewareFixture());
        $this->container->bindSingleton(CallReceivedMiddlewareCancelledFixture::class, static fn (): CallReceivedMiddlewareCancelledFixture => new CallReceivedMiddlewareCancelledFixture());
        $this->container->bindSingleton(CallReceivedMiddlewareCancellingFixture::class, static fn (): CallReceivedMiddlewareCancellingFixture => new CallReceivedMiddlewareCancellingFixture());
        $this->container->bindSingleton(CallReceivedMiddlewareChangedFixture::class, static fn (): CallReceivedMiddlewareChangedFixture => new CallReceivedMiddlewareChangedFixture());
        $this->container->bindSingleton(CallReceivedMiddlewareFixture::class, static fn (): CallReceivedMiddlewareFixture => new CallReceivedMiddlewareFixture());
        $this->container->bindSingleton(ResponseSentMiddlewareFixture::class, static fn (): ResponseSentMiddlewareFixture => new ResponseSentMiddlewareFixture());
        $this->container->bindSingleton(ResponseSentMiddlewareStoppedFixture::class, static fn (): ResponseSentMiddlewareStoppedFixture => new ResponseSentMiddlewareStoppedFixture());
        $this->container->bindSingleton(RouteDispatchedMiddlewareCancelledFixture::class, static fn (): RouteDispatchedMiddlewareCancelledFixture => new RouteDispatchedMiddlewareCancelledFixture());
        $this->container->bindSingleton(RouteDispatchedMiddlewareChangedFixture::class, static fn (): RouteDispatchedMiddlewareChangedFixture => new RouteDispatchedMiddlewareChangedFixture());
        $this->container->bindSingleton(RouteDispatchedMiddlewareFixture::class, static fn (): RouteDispatchedMiddlewareFixture => new RouteDispatchedMiddlewareFixture());
        $this->container->bindSingleton(RouteMatchedMiddlewareCancelledFixture::class, static fn (): RouteMatchedMiddlewareCancelledFixture => new RouteMatchedMiddlewareCancelledFixture());
        $this->container->bindSingleton(RouteMatchedMiddlewareCancellingFixture::class, static fn (): RouteMatchedMiddlewareCancellingFixture => new RouteMatchedMiddlewareCancellingFixture());
        $this->container->bindSingleton(RouteMatchedMiddlewareChangedFixture::class, static fn (): RouteMatchedMiddlewareChangedFixture => new RouteMatchedMiddlewareChangedFixture());
        $this->container->bindSingleton(RouteMatchedMiddlewareFixture::class, static fn (): RouteMatchedMiddlewareFixture => new RouteMatchedMiddlewareFixture());
        $this->container->bindSingleton(RouteNotMatchedMiddlewareCancelledFixture::class, static fn (): RouteNotMatchedMiddlewareCancelledFixture => new RouteNotMatchedMiddlewareCancelledFixture());
        $this->container->bindSingleton(RouteNotMatchedMiddlewareChangedFixture::class, static fn (): RouteNotMatchedMiddlewareChangedFixture => new RouteNotMatchedMiddlewareChangedFixture());
        $this->container->bindSingleton(RouteNotMatchedMiddlewareFixture::class, static fn (): RouteNotMatchedMiddlewareFixture => new RouteNotMatchedMiddlewareFixture());
        $this->container->bindSingleton(SendingResponseMiddlewareChangedFixture::class, static fn (): SendingResponseMiddlewareChangedFixture => new SendingResponseMiddlewareChangedFixture());
        $this->container->bindSingleton(SendingResponseMiddlewareFixture::class, static fn (): SendingResponseMiddlewareFixture => new SendingResponseMiddlewareFixture());
        $this->container->bindSingleton(ThrowableCaughtMiddlewareCancelledFixture::class, static fn (): ThrowableCaughtMiddlewareCancelledFixture => new ThrowableCaughtMiddlewareCancelledFixture());
        $this->container->bindSingleton(ThrowableCaughtMiddlewareChangedFixture::class, static fn (): ThrowableCaughtMiddlewareChangedFixture => new ThrowableCaughtMiddlewareChangedFixture());
        $this->container->bindSingleton(ThrowableCaughtMiddlewareFixture::class, static fn (): ThrowableCaughtMiddlewareFixture => new ThrowableCaughtMiddlewareFixture());
        $this->cancellation = new CancellationToken();

        $this->call = new ServiceCall(
            method: '/pkg.Service/Method',
            cancellation: $this->cancellation,
        );

        $this->response = ServiceResponse::ok();

        // The middleware stages take a route but never build one, so a contract double keeps this
        // component's tests independent of the routing component's concrete data class.
        $this->route = self::createStub(RouteContract::class);
    }
}
