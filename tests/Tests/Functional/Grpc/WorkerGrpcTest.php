<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Functional\Grpc;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Application\Data\GrpcConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\Abstract\WorkerGrpc;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\GrpcApplicationComponentProvider;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Metadata\Contract\MetadataContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Grpc\Server\Handler\Contract\ServiceHandlerContract;
use Valkyrja\Grpc\Throwable\Exception\CancelledException;
use Valkyrja\Tests\Abstract\TestCase;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\GrpcComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Grpc\Server\OutboundStreamFixture;

use function iterator_to_array;

/**
 * Drives the persistent-worker entry point the way an adapter does: bootstrap once, then dispatch
 * each call through an isolated child container with the wire write slotted between
 * SendingResponse and ResponseSent.
 */
#[RunTestsInSeparateProcesses]
final class WorkerGrpcTest extends TestCase
{
    private ApplicationContract $app;

    private ContainerData $data;

    protected function setUp(): void
    {
        ResponseSentMiddlewareFixture::resetCounter();

        $this->app = WorkerGrpc::bootstrap($this->config());

        // An application binds the middleware it schedules, so the test binds this one too.
        $this->app->getContainer()->bindSingleton(
            ResponseSentMiddlewareFixture::class,
            static fn (): ResponseSentMiddlewareFixture => new ResponseSentMiddlewareFixture()
        );

        $this->data = $this->app->getContainer()->getData();
    }

    public function testBootstrapForceResolvesTheServiceMapIntoTheParent(): void
    {
        $collection = $this->app->getContainer()->getSingleton(RouteCollectionContract::class);

        self::assertTrue($collection->has('/pkg.Greeter/SayHello'));
    }

    public function testDispatchWritesTheHandlerResponse(): void
    {
        $written = null;

        WorkerGrpc::dispatch(
            $this->app,
            $this->data,
            ServiceCall::unary('/pkg.Greeter/SayHello', 'ping'),
            static function (ServiceResponseContract $response) use (&$written): void {
                $written = $response;
            }
        );

        self::assertInstanceOf(ServiceResponseContract::class, $written);
        self::assertSame(StatusCode::OK, $written->getStatus()->getCode());
        self::assertSame(['hello'], iterator_to_array($written->getMessages(), false));
    }

    public function testDispatchKeepsCallScopedStateOutOfTheParentContainer(): void
    {
        $parent = $this->app->getContainer();

        WorkerGrpc::dispatch(
            $this->app,
            $this->data,
            ServiceCall::unary('/pkg.Greeter/SayHello', 'ping'),
            static function (): void {
            }
        );

        // The call and its response are set on the per-call child, so nothing bleeds into the
        // frozen parent and on into the next call.
        self::assertFalse($parent->isSingleton(ServiceCallContract::class));
        self::assertFalse($parent->isSingleton(ServiceResponseContract::class));
    }

    public function testTerminateStillRunsWhenTheWireWriteThrows(): void
    {
        $written = false;

        try {
            WorkerGrpc::dispatch(
                $this->app,
                $this->data,
                ServiceCall::unary('/pkg.Greeter/SayHello', 'ping'),
                static function () use (&$written): void {
                    $written = true;

                    throw new RuntimeException('wire blew up');
                }
            );

            self::fail('Expected the wire write to propagate');
        } catch (RuntimeException $exception) {
            self::assertSame('wire blew up', $exception->getMessage());
        }

        self::assertTrue($written);
    }

    public function testTerminateStillRunsWhenThePipelineThrows(): void
    {
        $written = false;

        // Debug mode is on, so the handler rethrows rather than mapping the throwable to a status.
        try {
            WorkerGrpc::dispatch(
                $this->app,
                $this->data,
                ServiceCall::unary('/pkg.Cancelling/Cancel', 'ping'),
                static function () use (&$written): void {
                    $written = true;
                }
            );

            self::fail('Expected the handler throwable to propagate');
        } catch (CancelledException $exception) {
            self::assertSame(CancellationReason::CLIENT_CANCELLED, $exception->getReason());
        }

        // The wire write is skipped, and ResponseSent still runs, so the call is not left open.
        self::assertFalse($written);
        self::assertSame(1, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testDispatchStreamingTerminatesWhenThePipelineThrows(): void
    {
        $outbound = new OutboundStreamFixture();

        try {
            WorkerGrpc::dispatchStreaming(
                $this->app,
                $this->data,
                static fn (callable $sink): ServiceCallContract => new ServiceCall(
                    method: '/pkg.Cancelling/Cancel',
                    messages: ['one'],
                    sink: $sink,
                ),
                $outbound
            );

            self::fail('Expected the handler throwable to propagate');
        } catch (CancelledException) {
            // The guard must not swallow it.
        }

        // Neither the headers nor the close reached the wire, and ResponseSent still ran.
        self::assertSame([], $outbound->events);
        self::assertSame(1, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testDispatchStreamingClosesAnOpenedStreamWhenThePipelineThrows(): void
    {
        $outbound = new OutboundStreamFixture();

        try {
            WorkerGrpc::dispatchStreaming(
                $this->app,
                $this->data,
                static fn (callable $sink): ServiceCallContract => new ServiceCall(
                    method: '/pkg.Cancelling/EmitThenFail',
                    messages: ['one'],
                    sink: $sink,
                ),
                $outbound
            );

            self::fail('Expected the handler throwable to propagate');
        } catch (CancelledException) {
            // The guard must not swallow it.
        }

        // The client already holds headers and a message, so it must also get a trailing status.
        self::assertSame(['headers', 'message', 'close'], $outbound->events);
        self::assertNotNull($outbound->terminal);
        self::assertSame(StatusCode::INTERNAL, $outbound->terminal->getStatus()->getCode());
        self::assertSame(1, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testAFailedRecoveryCloseKeepsTheHandlersThrowable(): void
    {
        $outbound               = new OutboundStreamFixture();
        $outbound->throwOnClose = new RuntimeException('close blew up too');

        try {
            WorkerGrpc::dispatchStreaming(
                $this->app,
                $this->data,
                static fn (callable $sink): ServiceCallContract => new ServiceCall(
                    method: '/pkg.Cancelling/EmitThenFail',
                    messages: ['one'],
                    sink: $sink,
                ),
                $outbound
            );

            self::fail('Expected the handler throwable to propagate');
        } catch (CancelledException $exception) {
            // The recovery close failed in turn, and the first throwable is still the one raised.
            self::assertSame('the handler stopped after emitting', $exception->getMessage());
        }

        self::assertSame(['headers', 'message', 'close'], $outbound->events);
        self::assertSame(1, ResponseSentMiddlewareFixture::getAndResetCounter());
    }

    public function testAnUnknownMethodIsUnimplemented(): void
    {
        $written = null;

        WorkerGrpc::dispatch(
            $this->app,
            $this->data,
            ServiceCall::unary('/pkg.Greeter/Nope', 'ping'),
            static function (ServiceResponseContract $response) use (&$written): void {
                $written = $response;
            }
        );

        self::assertInstanceOf(ServiceResponseContract::class, $written);
        self::assertSame(StatusCode::UNIMPLEMENTED, $written->getStatus()->getCode());
    }

    public function testDispatchStreamingOpensTheStreamEvenWhenTheHandlerEmitsNothing(): void
    {
        $outbound = new OutboundStreamFixture();

        WorkerGrpc::dispatchStreaming(
            $this->app,
            $this->data,
            static fn (callable $sink): ServiceCallContract => new ServiceCall(
                method: '/pkg.Greeter/Chat',
                messages: ['one'],
                sink: $sink,
            ),
            $outbound
        );

        // The handler returns its messages rather than pushing them, so the stream opens at close
        // and the open/close pairing stays symmetric.
        self::assertSame(['headers', 'close'], $outbound->events);
        self::assertInstanceOf(MetadataContract::class, $outbound->headers);
        self::assertInstanceOf(ServiceResponseContract::class, $outbound->terminal);
        self::assertSame(StatusCode::OK, $outbound->terminal->getStatus()->getCode());
    }

    public function testDispatchStreamingTerminatesWhenTheWireCloseThrows(): void
    {
        $outbound               = new OutboundStreamFixture();
        $outbound->throwOnClose = new RuntimeException('close blew up');

        try {
            WorkerGrpc::dispatchStreaming(
                $this->app,
                $this->data,
                static fn (callable $sink): ServiceCallContract => new ServiceCall(
                    method: '/pkg.Greeter/Chat',
                    messages: ['one'],
                    sink: $sink,
                ),
                $outbound
            );

            self::fail('Expected the wire close to propagate');
        } catch (RuntimeException $exception) {
            self::assertSame('close blew up', $exception->getMessage());
        }

        // The close ran and threw, and terminate() still ran in the finally.
        self::assertSame(['headers', 'close'], $outbound->events);
        self::assertInstanceOf(ServiceResponseContract::class, $outbound->terminal);
    }

    public function testDispatchStreamingPushesEachHandlerEmitAfterTheHeaders(): void
    {
        $outbound = new OutboundStreamFixture();

        WorkerGrpc::dispatchStreaming(
            $this->app,
            $this->data,
            static fn (callable $sink): ServiceCallContract => new ServiceCall(
                method: '/pkg.Echo/Echo',
                messages: ['one', 'two'],
                sink: $sink,
            ),
            $outbound
        );

        // SendingResponse fires once, at the first emit, and the close follows the last message.
        self::assertSame(['headers', 'message', 'message', 'close'], $outbound->events);
        self::assertSame(['one', 'two'], $outbound->messages);
        self::assertSame(StatusCode::OK, $outbound->terminal->getStatus()->getCode());
    }

    public function testOpenStreamCommitsHeadersExactlyOnce(): void
    {
        $container = WorkerGrpc::getChildContainer($this->app, $this->data);
        $child     = WorkerGrpc::getChildApplication($this->app, $container);

        WorkerGrpc::bootstrapChildContainer($child, $container);

        $handler  = $container->getSingleton(ServiceHandlerContract::class);
        $outbound = new OutboundStreamFixture();
        $call     = ServiceCall::unary('/pkg.Greeter/SayHello', 'ping');
        $opened   = false;

        WorkerGrpc::openStream($handler, $call, $outbound, $opened);
        WorkerGrpc::openStream($handler, $call, $outbound, $opened);

        self::assertTrue($opened);
        self::assertSame(['headers'], $outbound->events);
    }

    private function config(): GrpcConfigContract
    {
        return new GrpcConfig(
            dir: Directory::$basePath,
            debugMode: true,
            providers: [
                new GrpcApplicationComponentProvider(),
                new GrpcComponentProviderFixture(),
            ],
            responseSentMiddleware: [ResponseSentMiddlewareFixture::class],
        );
    }
}
