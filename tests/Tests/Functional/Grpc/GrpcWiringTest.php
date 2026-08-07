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
use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Application\Data\GrpcConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\Grpc;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\GrpcApplicationComponentProvider;
use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Grpc\Server\Handler\Contract\ServiceHandlerContract;
use Valkyrja\Tests\Abstract\TestCase;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\CounterControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\GrpcComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\GrpcRouteProviderWithRoutesFixture;

use function count;
use function iterator_to_array;

/**
 * Boots the whole gRPC stack from a Service-attributed controller and dispatches real calls
 * through it, so the provider wiring, the service map, the router, and the kernel are exercised
 * together rather than in isolation.
 *
 * Each test boots in its own process: the service map is only scanned in debug mode, which also
 * installs the debug throwable handler, and that handler would otherwise outlive the test.
 */
#[RunTestsInSeparateProcesses]
final class GrpcWiringTest extends TestCase
{
    public function testBootstrapPopulatesTheServiceMapFromTheAttributedController(): void
    {
        $collection = $this->bootstrap()->getContainer()->getSingleton(RouteCollectionContract::class);

        self::assertTrue($collection->has('/pkg.Greeter/SayHello'));
        self::assertTrue($collection->has('/pkg.Greeter/Chat'));
        self::assertTrue($collection->has('/pkg.Greeter/Guarded'));
        self::assertTrue($collection->has(GrpcRouteProviderWithRoutesFixture::METHOD));
    }

    public function testTheServiceMapSkipsAnUnattributedMethod(): void
    {
        $collection = $this->bootstrap()->getContainer()->getSingleton(RouteCollectionContract::class);

        self::assertFalse($collection->has('/pkg.Greeter/notAnRpc'));
    }

    public function testHandlesAUnaryCallEndToEnd(): void
    {
        $response = Grpc::handle(
            $this->config(),
            ServiceCall::unary('/pkg.Greeter/SayHello', 'ping')
        );

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame(['hello'], iterator_to_array($response->getMessages(), false));
    }

    public function testAnUnknownMethodIsUnimplemented(): void
    {
        $response = Grpc::handle(
            $this->config(),
            ServiceCall::unary('/pkg.Greeter/Nope', 'ping')
        );

        self::assertSame(StatusCode::UNIMPLEMENTED, $response->getStatus()->getCode());
    }

    public function testDispatchesAPreBuiltRoute(): void
    {
        $response = Grpc::handle(
            $this->config(),
            ServiceCall::unary(GrpcRouteProviderWithRoutesFixture::METHOD, 'ping')
        );

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame(['prebuilt'], iterator_to_array($response->getMessages(), false));
    }

    public function testTheStreamingFlagsSurviveTheRoundTrip(): void
    {
        $collection = $this->bootstrap()->getContainer()->getSingleton(RouteCollectionContract::class);

        $route = $collection->get('/pkg.Greeter/Chat');

        self::assertTrue($route->isClientStreaming());
        self::assertTrue($route->isServerStreaming());
    }

    public function testPerRouteMiddlewareFiresOnEveryStageIncludingTheKernelOnlyOnes(): void
    {
        AllMiddlewareFixture::resetCounter();

        $app       = $this->bootstrap();
        $container = $app->getContainer();

        // An application binds the middleware it schedules, so the test binds this one too.
        $container->bindSingleton(AllMiddlewareFixture::class, static fn (): AllMiddlewareFixture => new AllMiddlewareFixture());

        $handler = $container->getSingleton(ServiceHandlerContract::class);

        $call = ServiceCall::unary('/pkg.Greeter/Guarded', 'ping');

        $response = $handler->run($call);

        $handler->terminate($call, $response);

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        // RouteMatched, RouteDispatched, SendingResponse and ResponseSent all fire. The last two
        // are registered by the Router but consumed by the ServiceHandler, which only works because
        // both resolve the same stage-handler singletons.
        self::assertSame(4, AllMiddlewareFixture::getAndResetCounter());
    }

    public function testACancelledCallFastExitsButStillSendsAndTerminates(): void
    {
        AllMiddlewareFixture::resetCounter();

        $app       = $this->bootstrap();
        $container = $app->getContainer();

        $handler = $container->getSingleton(ServiceHandlerContract::class);

        $cancellation = new CancellationToken();
        $cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $call = new ServiceCall(
            method: '/pkg.Greeter/Guarded',
            messages: ['ping'],
            cancellation: $cancellation,
        );

        $response = $handler->run($call);

        $handler->terminate($call, $response);

        self::assertSame(StatusCode::CANCELLED, $response->getStatus()->getCode());
        // Request-processing stages are skipped; the always-run SendingResponse and ResponseSent
        // stages still fire, but only after the route's middleware was registered onto them.
        self::assertSame(0, AllMiddlewareFixture::getAndResetCounter());
    }

    public function testAConsumerThatStopsReadingStopsTheStreamingHandler(): void
    {
        CounterControllerFixture::reset();

        $response = Grpc::handle(
            $this->config(),
            ServiceCall::unary('/pkg.Counter/Count', 'go')
        );

        $drained = [];

        foreach ($response->getMessages() as $message) {
            $drained[] = $message;

            // Stand in for an adapter that pauses the drain because the transport cannot accept
            // another message — the peer is alive, it has just stopped consuming.
            if (count($drained) === 3) {
                break;
            }
        }

        self::assertSame(['message 1', 'message 2', 'message 3'], $drained);
        // The handler produced exactly what was consumed. `maxInboundMessages` bounds the inbound
        // direction only; outbound stays bounded because the drain never runs ahead of the reader.
        self::assertSame(3, CounterControllerFixture::$produced);
    }

    public function testTheRouteHandlerInvokesTheAttributedControllerMethod(): void
    {
        $app        = $this->bootstrap();
        $collection = $app->getContainer()->getSingleton(RouteCollectionContract::class);

        $route   = $collection->get('/pkg.Greeter/SayHello');
        $handler = $route->getHandler();

        $response = $handler($app->getContainer(), $route);

        self::assertSame(['hello'], iterator_to_array($response->getMessages(), false));
    }

    private function config(bool $debugMode = true): GrpcConfigContract
    {
        return new GrpcConfig(
            dir: Directory::$basePath,
            debugMode: $debugMode,
            providers: [
                new GrpcApplicationComponentProvider(),
                new GrpcComponentProviderFixture(),
            ],
        );
    }

    private function bootstrap(): ApplicationContract
    {
        return Grpc::bootstrap($this->config());
    }
}
