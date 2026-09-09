<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Server\Handler;

use RuntimeException;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Middleware\Handler\CallReceivedHandler;
use Valkyrja\Grpc\Middleware\Handler\ResponseSentHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Grpc\Middleware\Handler\SendingResponseHandler;
use Valkyrja\Grpc\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Grpc\Routing\Collection\RouteCollection;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Grpc\Routing\Dispatcher\Router;
use Valkyrja\Grpc\Server\Handler\ServiceHandler;
use Valkyrja\Grpc\Throwable\Exception\CancelledException;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\CallReceivedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ServiceHandlerTest extends TestCase
{
    private const string METHOD = '/pkg.Service/Method';

    private Container $container;

    private RouteCollection $collection;

    private CallReceivedHandler $callReceivedHandler;

    private ThrowableCaughtHandler $throwableCaughtHandler;

    private SendingResponseHandler $sendingResponseHandler;

    private ResponseSentHandler $responseSentHandler;

    protected function setUp(): void
    {
        $this->container              = new Container();

        // Every middleware a stage schedules is bound, the same way an application binds its own.
        $this->container->bindSingleton(CallReceivedMiddlewareChangedFixture::class, static fn (): CallReceivedMiddlewareChangedFixture => new CallReceivedMiddlewareChangedFixture());
        $this->container->bindSingleton(CallReceivedMiddlewareFixture::class, static fn (): CallReceivedMiddlewareFixture => new CallReceivedMiddlewareFixture());
        $this->container->bindSingleton(ResponseSentMiddlewareFixture::class, static fn (): ResponseSentMiddlewareFixture => new ResponseSentMiddlewareFixture());
        $this->container->bindSingleton(SendingResponseMiddlewareFixture::class, static fn (): SendingResponseMiddlewareFixture => new SendingResponseMiddlewareFixture());
        $this->container->bindSingleton(ThrowableCaughtMiddlewareFixture::class, static fn (): ThrowableCaughtMiddlewareFixture => new ThrowableCaughtMiddlewareFixture());
        $this->collection             = new RouteCollection();
        $this->callReceivedHandler    = new CallReceivedHandler($this->container);
        $this->throwableCaughtHandler = new ThrowableCaughtHandler($this->container);
        $this->sendingResponseHandler = new SendingResponseHandler($this->container);
        $this->responseSentHandler    = new ResponseSentHandler($this->container);
    }

    public function testHandlesAMatchedCall(): void
    {
        $this->addRoute(static fn (): ServiceResponseContract => ServiceResponse::ok('handled'));

        $response = $this->handler()->handle(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame(['handled'], $response->getMessages());
    }

    public function testAnUnknownMethodIsUnimplemented(): void
    {
        $response = $this->handler()->handle(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::UNIMPLEMENTED, $response->getStatus()->getCode());
    }

    public function testPutsTheCallAndResponseInTheContainer(): void
    {
        $this->addRoute(static fn (): ServiceResponseContract => ServiceResponse::ok());

        $call     = new ServiceCall(self::METHOD);
        $response = $this->handler()->handle($call);

        self::assertInstanceOf(ServiceCallContract::class, $this->container->getSingleton(ServiceCallContract::class));
        self::assertSame($response, $this->container->getSingleton(ServiceResponseContract::class));
    }

    public function testRunsTheCallReceivedStage(): void
    {
        CallReceivedMiddlewareFixture::resetCounter();

        $this->callReceivedHandler->add(CallReceivedMiddlewareFixture::class);

        $this->addRoute(static fn (): ServiceResponseContract => ServiceResponse::ok());

        $this->handler()->handle(new ServiceCall(self::METHOD));

        self::assertSame(1, CallReceivedMiddlewareFixture::getAndResetCounter());
    }

    public function testAShortCircuitingCallReceivedMiddlewareSkipsTheRouter(): void
    {
        CallReceivedMiddlewareChangedFixture::resetCounter();

        $this->callReceivedHandler->add(CallReceivedMiddlewareChangedFixture::class);

        $handlerRan = false;

        $this->addRoute(static function () use (&$handlerRan): ServiceResponseContract {
            $handlerRan = true;

            return ServiceResponse::ok();
        });

        $response = $this->handler()->handle(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::ABORTED, $response->getStatus()->getCode());
        self::assertFalse($handlerRan);
        self::assertSame(1, CallReceivedMiddlewareChangedFixture::getAndResetCounter());
    }

    public function testTheEntryPreCheckFastExitsAnAlreadyCancelledCall(): void
    {
        CallReceivedMiddlewareFixture::resetCounter();

        $this->callReceivedHandler->add(CallReceivedMiddlewareFixture::class);

        $cancellation = new CancellationToken();
        $cancellation->cancel(CancellationReason::DEADLINE_EXCEEDED);

        $response = $this->handler()->handle(
            new ServiceCall(self::METHOD, cancellation: $cancellation)
        );

        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $response->getStatus()->getCode());
        self::assertSame(0, CallReceivedMiddlewareFixture::getAndResetCounter());
    }

    public function testAnUncaughtThrowableBecomesInternal(): void
    {
        $this->addRoute(static function (): ServiceResponseContract {
            throw new RuntimeException('boom');
        });

        $response = $this->handler()->handle(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::INTERNAL, $response->getStatus()->getCode());
    }

    public function testACancelledExceptionBecomesCancelled(): void
    {
        $this->addRoute(static function (): ServiceResponseContract {
            throw new CancelledException('stopped');
        });

        $response = $this->handler()->handle(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::CANCELLED, $response->getStatus()->getCode());
    }

    public function testACancelledExceptionCarryingADeadlineReasonBecomesDeadlineExceeded(): void
    {
        $this->addRoute(static function (): ServiceResponseContract {
            throw new CancelledException('stopped', CancellationReason::DEADLINE_EXCEEDED);
        });

        $response = $this->handler()->handle(new ServiceCall(self::METHOD));

        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $response->getStatus()->getCode());
    }

    public function testAThrownThrowableRunsTheThrowableCaughtStage(): void
    {
        ThrowableCaughtMiddlewareFixture::resetCounter();

        $this->throwableCaughtHandler->add(ThrowableCaughtMiddlewareFixture::class);

        $this->addRoute(static function (): ServiceResponseContract {
            throw new RuntimeException('boom');
        });

        $this->handler()->handle(new ServiceCall(self::METHOD));

        self::assertSame(1, ThrowableCaughtMiddlewareFixture::getAndResetCounter());
    }

    public function testDebugModeRethrows(): void
    {
        $this->addRoute(static function (): ServiceResponseContract {
            throw new RuntimeException('boom');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->handler(debug: true)->handle(new ServiceCall(self::METHOD));
    }

    public function testSendingRunsTheSendingResponseStage(): void
    {
        SendingResponseMiddlewareFixture::resetCounter();

        $this->sendingResponseHandler->add(SendingResponseMiddlewareFixture::class);

        $call     = new ServiceCall(self::METHOD);
        $response = ServiceResponse::ok();

        $sent = $this->handler()->sending($call, $response);

        self::assertSame($response, $sent);
        self::assertSame($sent, $this->container->getSingleton(ServiceResponseContract::class));
        self::assertSame(1, SendingResponseMiddlewareFixture::getAndResetCounter());
    }

    public function testTerminateRunsTheResponseSentStage(): void
    {
        ResponseSentMiddlewareFixture::resetCounter();

        $this->responseSentHandler->add(ResponseSentMiddlewareFixture::class);

        $this->handler()->terminate(new ServiceCall(self::METHOD), ServiceResponse::ok());

        self::assertSame(1, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testRunBundlesHandleAndSending(): void
    {
        SendingResponseMiddlewareFixture::resetCounter();

        $this->sendingResponseHandler->add(SendingResponseMiddlewareFixture::class);

        $this->addRoute(static fn (): ServiceResponseContract => ServiceResponse::ok('handled'));

        $response = $this->handler()->run(new ServiceCall(self::METHOD));

        self::assertSame(['handled'], $response->getMessages());
        self::assertSame(1, SendingResponseMiddlewareFixture::getAndResetCounter());
    }

    public function testSendingAndTerminateStillRunForACancelledCall(): void
    {
        SendingResponseMiddlewareFixture::resetCounter();
        ResponseSentMiddlewareFixture::resetCounter();

        $this->sendingResponseHandler->add(SendingResponseMiddlewareFixture::class);
        $this->responseSentHandler->add(ResponseSentMiddlewareFixture::class);

        $cancellation = new CancellationToken();
        $cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $call    = new ServiceCall(self::METHOD, cancellation: $cancellation);
        $handler = $this->handler();

        $response = $handler->run($call);

        $handler->terminate($call, $response);

        self::assertSame(StatusCode::CANCELLED, $response->getStatus()->getCode());
        self::assertSame(1, SendingResponseMiddlewareFixture::getAndResetCounter());
        self::assertSame(1, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testRunsWithTheDefaultStageHandlers(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(self::METHOD, static fn (): ServiceResponseContract => ServiceResponse::ok('hello')));

        $handler = new ServiceHandler(
            container: new Container(),
            router: new Router(collection: $collection),
        );

        $response = $handler->run(ServiceCall::unary(self::METHOD, 'ping'));

        self::assertSame(['hello'], $response->getMessages());
        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
    }

    private function handler(bool $debug = false): ServiceHandler
    {
        $router = new Router(
            container: $this->container,
            collection: $this->collection,
            routeMatchedHandler: new RouteMatchedHandler($this->container),
            routeNotMatchedHandler: new RouteNotMatchedHandler($this->container),
            routeDispatchedHandler: new RouteDispatchedHandler($this->container),
            throwableCaughtHandler: $this->throwableCaughtHandler,
            sendingResponseHandler: $this->sendingResponseHandler,
            responseSentHandler: $this->responseSentHandler,
        );

        return new ServiceHandler(
            container: $this->container,
            router: $router,
            callReceivedHandler: $this->callReceivedHandler,
            throwableCaughtHandler: $this->throwableCaughtHandler,
            sendingResponseHandler: $this->sendingResponseHandler,
            responseSentHandler: $this->responseSentHandler,
            debug: $debug,
        );
    }

    private function addRoute(callable $handler): void
    {
        $this->collection->add(new Route(self::METHOD, $handler));
    }
}
