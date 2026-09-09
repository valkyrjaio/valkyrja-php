<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Dispatcher;

use Valkyrja\Container\Manager\Container;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Middleware\Handler\ResponseSentHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\SendingResponseHandler;
use Valkyrja\Grpc\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Grpc\Routing\Collection\RouteCollection;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Grpc\Routing\Dispatcher\Router;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteNotMatchedMiddlewareFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function reset;

final class RouterTest extends TestCase
{
    private const string METHOD = '/pkg.Service/Method';

    private Container $container;

    private RouteCollection $collection;

    private RouteMatchedHandler $routeMatchedHandler;

    private RouteNotMatchedHandler $routeNotMatchedHandler;

    private RouteDispatchedHandler $routeDispatchedHandler;

    private ThrowableCaughtHandler $throwableCaughtHandler;

    private SendingResponseHandler $sendingResponseHandler;

    private ResponseSentHandler $responseSentHandler;

    protected function setUp(): void
    {
        $this->container              = new Container();

        // Every middleware a route schedules is bound, the same way an application binds its own.
        $this->container->bindSingleton(AllMiddlewareFixture::class, static fn (): AllMiddlewareFixture => new AllMiddlewareFixture());
        $this->container->bindSingleton(RouteDispatchedMiddlewareFixture::class, static fn (): RouteDispatchedMiddlewareFixture => new RouteDispatchedMiddlewareFixture());
        $this->container->bindSingleton(RouteMatchedMiddlewareChangedFixture::class, static fn (): RouteMatchedMiddlewareChangedFixture => new RouteMatchedMiddlewareChangedFixture());
        $this->container->bindSingleton(RouteMatchedMiddlewareFixture::class, static fn (): RouteMatchedMiddlewareFixture => new RouteMatchedMiddlewareFixture());
        $this->container->bindSingleton(RouteNotMatchedMiddlewareFixture::class, static fn (): RouteNotMatchedMiddlewareFixture => new RouteNotMatchedMiddlewareFixture());
        $this->collection             = new RouteCollection();
        $this->routeMatchedHandler    = new RouteMatchedHandler($this->container);
        $this->routeNotMatchedHandler = new RouteNotMatchedHandler($this->container);
        $this->routeDispatchedHandler = new RouteDispatchedHandler($this->container);
        $this->throwableCaughtHandler = new ThrowableCaughtHandler($this->container);
        $this->sendingResponseHandler = new SendingResponseHandler($this->container);
        $this->responseSentHandler    = new ResponseSentHandler($this->container);
    }

    public function testAnUnknownMethodRoutesToTheUnimplementedTerminal(): void
    {
        $response = $this->router()->dispatch(new ServiceCall('/pkg.Service/Missing'));

        self::assertSame(StatusCode::UNIMPLEMENTED, $response->getStatus()->getCode());
    }

    public function testAnUnknownMethodRunsTheRouteNotMatchedStage(): void
    {
        RouteNotMatchedMiddlewareFixture::resetCounter();

        $this->routeNotMatchedHandler->add(RouteNotMatchedMiddlewareFixture::class);

        $response = $this->router()->dispatch(new ServiceCall('/pkg.Service/Missing'));

        self::assertSame(StatusCode::UNIMPLEMENTED, $response->getStatus()->getCode());
        self::assertSame(1, RouteNotMatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testDispatchesAMatchedRoute(): void
    {
        $this->collection->add($this->route());

        $response = $this->router()->dispatch(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame(['handled'], $response->getMessages());
    }

    public function testAttachesTheResolvedRouteToTheCallInTheContainer(): void
    {
        $route = $this->route();

        $this->collection->add($route);

        $this->router()->dispatch(new ServiceCall(self::METHOD));

        $call = $this->container->getSingleton(ServiceCallContract::class);

        self::assertTrue($call->hasRoute());
        self::assertSame($route, $call->getRoute());
        self::assertSame($route, $this->container->getSingleton(RouteContract::class));
    }

    public function testRunsTheRouteMatchedAndRouteDispatchedStages(): void
    {
        RouteMatchedMiddlewareFixture::resetCounter();
        RouteDispatchedMiddlewareFixture::resetCounter();

        $this->routeMatchedHandler->add(RouteMatchedMiddlewareFixture::class);
        $this->routeDispatchedHandler->add(RouteDispatchedMiddlewareFixture::class);

        $this->collection->add($this->route());

        $response = $this->router()->dispatch(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame(1, RouteMatchedMiddlewareFixture::getAndResetCounter());
        self::assertSame(1, RouteDispatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingRouteMatchedMiddlewareSkipsTheHandler(): void
    {
        RouteMatchedMiddlewareChangedFixture::resetCounter();

        $this->routeMatchedHandler->add(RouteMatchedMiddlewareChangedFixture::class);

        $this->collection->add($this->route());

        $response = $this->router()->dispatch(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::ABORTED, $response->getStatus()->getCode());
        self::assertSame(1, RouteMatchedMiddlewareChangedFixture::getAndResetCounter());
    }

    public function testRegistersPerRouteMiddlewareOntoEveryStageHandler(): void
    {
        $route = new Route(
            method: self::METHOD,
            handler: static fn (): ServiceResponseContract => ServiceResponse::ok(),
            routeMatchedMiddleware: [AllMiddlewareFixture::class],
            routeDispatchedMiddleware: [AllMiddlewareFixture::class],
            throwableCaughtMiddleware: [AllMiddlewareFixture::class],
            sendingResponseMiddleware: [AllMiddlewareFixture::class],
            responseSentMiddleware: [AllMiddlewareFixture::class],
        );

        $this->collection->add($route);

        AllMiddlewareFixture::resetCounter();

        $this->router()->dispatch(new ServiceCall(self::METHOD));

        // RouteMatched and RouteDispatched run inside dispatch; the SendingResponse, ResponseSent,
        // and ThrowableCaught registrations are consumed later by the kernel, which shares these
        // very handler instances.
        self::assertSame(2, AllMiddlewareFixture::getAndResetCounter());

        $this->sendingResponseHandler->sendingResponse(new ServiceCall(self::METHOD), ServiceResponse::ok());

        self::assertSame(1, AllMiddlewareFixture::getAndResetCounter());

        $this->responseSentHandler->responseSent(new ServiceCall(self::METHOD), ServiceResponse::ok());

        self::assertSame(1, AllMiddlewareFixture::getAndResetCounter());
    }

    public function testThePreCheckFastExitsAnAlreadyCancelledCall(): void
    {
        $cancellation = new CancellationToken();
        $cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $handlerRan = false;

        $this->collection->add(
            new Route(
                method: self::METHOD,
                handler: static function () use (&$handlerRan): ServiceResponseContract {
                    $handlerRan = true;

                    return ServiceResponse::ok();
                },
            )
        );

        $response = $this->router()->dispatch(
            new ServiceCall(self::METHOD, cancellation: $cancellation)
        );

        self::assertSame(StatusCode::CANCELLED, $response->getStatus()->getCode());
        self::assertFalse($handlerRan);
    }

    public function testThePostCheckFastExitsACancellationRaisedByTheHandler(): void
    {
        RouteDispatchedMiddlewareFixture::resetCounter();

        $this->routeDispatchedHandler->add(RouteDispatchedMiddlewareFixture::class);

        $cancellation = new CancellationToken();

        $this->collection->add(
            new Route(
                method: self::METHOD,
                handler: static function () use ($cancellation): ServiceResponseContract {
                    $cancellation->cancel(CancellationReason::DEADLINE_EXCEEDED);

                    return ServiceResponse::ok('too late');
                },
            )
        );

        $response = $this->router()->dispatch(
            new ServiceCall(self::METHOD, cancellation: $cancellation)
        );

        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $response->getStatus()->getCode());
        // The overlay preserves what the handler did manage to produce.
        self::assertSame(['too late'], $response->getMessages());
        // RouteDispatched is a request-processing stage, so the fast-exit skips it.
        self::assertSame(0, RouteDispatchedMiddlewareFixture::getAndResetCounter());
    }

    public function testDispatchRouteCanBeCalledDirectly(): void
    {
        $route = $this->route();

        $response = $this->router()->dispatchRoute(new ServiceCall(self::METHOD), $route);

        self::assertSame(['handled'], $response->getMessages());
    }

    public function testTheRouteMatchedMiddlewareCanReplaceTheRoute(): void
    {
        $this->collection->add($this->route());

        $this->router()->dispatch(new ServiceCall(self::METHOD));

        $all = $this->collection->all();

        self::assertSame(self::METHOD, reset($all)->getMethod());
    }

    private function router(): Router
    {
        return new Router(
            container: $this->container,
            collection: $this->collection,
            routeMatchedHandler: $this->routeMatchedHandler,
            routeNotMatchedHandler: $this->routeNotMatchedHandler,
            routeDispatchedHandler: $this->routeDispatchedHandler,
            throwableCaughtHandler: $this->throwableCaughtHandler,
            sendingResponseHandler: $this->sendingResponseHandler,
            responseSentHandler: $this->responseSentHandler,
        );
    }

    private function route(string ...$middleware): Route
    {
        return new Route(
            method: self::METHOD,
            handler: static fn (): ServiceResponseContract => ServiceResponse::ok('handled'),
            routeMatchedMiddleware: $middleware,
        );
    }
}
