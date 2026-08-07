<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\Abstract;

use Closure;
use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Application\Kernel\ChildApplication;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\ChildContainer;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Message\Stream\Contract\OutboundStreamContract;
use Valkyrja\Grpc\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Grpc\Server\Handler\Contract\ServiceHandlerContract;

abstract class WorkerGrpc extends App
{
    /**
     * Bootstrap the application once at worker startup.
     *
     * Call this once before the worker call loop begins. The returned application is frozen after
     * this call — its container must not be written to again. Pass it to dispatch() for every
     * subsequent call.
     */
    public static function bootstrap(GrpcConfigContract $config): ApplicationContract
    {
        $app = static::start(
            config: $config,
        );

        $container = $app->getContainer();

        static::bootstrapThrowableHandler($app, $container);

        // Force-resolve services that must live in the parent's frozen map.
        static::bootstrapParentServices($app);

        return $app;
    }

    /**
     * Handle a single call using an isolated child application.
     *
     * Resolves the ServiceHandler, runs the pipeline through SendingResponse, hands the response to
     * the writer to write to the wire, then runs ResponseSent.
     *
     * @param ApplicationContract                   $app    The frozen parent application
     * @param ContainerData                         $data   The container data snapshot
     * @param ServiceCallContract                   $call   The inbound call
     * @param Closure(ServiceResponseContract):void $writer Writes the response to the wire
     */
    public static function dispatch(ApplicationContract $app, ContainerData $data, ServiceCallContract $call, Closure $writer): void
    {
        $childContainer = static::getChildContainer($app, $data);
        $childApp       = static::getChildApplication($app, $childContainer);

        static::bootstrapChildContainer($childApp, $childContainer);

        $handler = $childContainer->getSingleton(ServiceHandlerContract::class);

        $response = $handler->handle($call);
        $response = $handler->sending($call, $response);

        try {
            $writer($response);
        } finally {
            // ResponseSent middleware must run even when the wire write blows up, so per-call
            // resources are released and observers still see the call complete.
            $handler->terminate($call, $response);
        }
    }

    /**
     * Handle a single streaming-model (bidirectional) call.
     *
     * Unlike dispatch(), the handler is invoked immediately — not after half-close — and emits
     * messages through the call's push sink while it reads live inbound; the adapter runs this on
     * its own per-call execution unit.
     *
     * The pipeline still runs once per call: SendingResponse fires once at stream open (the first
     * emit, or the close when the handler emits nothing) against an OK shell whose initial metadata
     * becomes the response headers; the handler's returned terminal response supplies the final
     * status and trailing metadata; and ResponseSent fires once at close.
     *
     * @param ApplicationContract                              $app         The frozen parent application
     * @param ContainerData                                    $data        The container data snapshot
     * @param Closure(Closure(mixed):void):ServiceCallContract $callFactory Builds the streaming call around the supplied outbound sink
     * @param OutboundStreamContract                           $outbound    The transport-side outbound stream
     */
    public static function dispatchStreaming(ApplicationContract $app, ContainerData $data, Closure $callFactory, OutboundStreamContract $outbound): void
    {
        $childContainer = static::getChildContainer($app, $data);
        $childApp       = static::getChildApplication($app, $childContainer);

        static::bootstrapChildContainer($childApp, $childContainer);

        $handler = $childContainer->getSingleton(ServiceHandlerContract::class);

        $call   = null;
        $opened = false;

        $call = $callFactory(
            static function (mixed $message) use ($handler, &$call, $outbound, &$opened): void {
                // $call is assigned before the handler — and thus any emit — runs.
                /** @var ServiceCallContract $call */
                static::openStream($handler, $call, $outbound, $opened);

                $outbound->sendMessage($message);
            }
        );

        $terminal = $handler->handle($call);

        // Open the stream once even if the handler emitted nothing, so SendingResponse always fires
        // before the close and the open/close pairing stays symmetric.
        static::openStream($handler, $call, $outbound, $opened);

        try {
            $outbound->close($terminal);
        } finally {
            $handler->terminate($call, $terminal);
        }
    }

    /**
     * Commit the stream's initial headers exactly once.
     *
     * SendingResponse governs the headers; at open the final status is unknown, so it runs against
     * an OK shell whose initial metadata is sent as the response headers.
     *
     * @param bool $opened Whether the stream has already been opened
     */
    public static function openStream(ServiceHandlerContract $handler, ServiceCallContract $call, OutboundStreamContract $outbound, bool &$opened): void
    {
        if ($opened) {
            return;
        }

        $opened = true;

        $shell = $handler->sending($call, ServiceResponse::ok());

        $outbound->sendHeaders($shell->getInitialMetadata());
    }

    /**
     * Get a child application for the call.
     */
    public static function getChildApplication(ApplicationContract $app, ContainerContract $container): ApplicationContract
    {
        return new ChildApplication($app, $container);
    }

    /**
     * Get a child container for the call.
     */
    public static function getChildContainer(ApplicationContract $app, ContainerData $data): ContainerContract
    {
        return new ChildContainer($app->getContainer(), $data);
    }

    /**
     * Bootstrap a child container with the call-scoped singletons.
     */
    public static function bootstrapChildContainer(ApplicationContract $app, ContainerContract $container): void
    {
        $container->setSingleton(ApplicationContract::class, $app);
        $container->setSingleton(ContainerContract::class, $container);
    }

    /**
     * Force-resolve the service map so it is cached in the frozen parent rather than rebuilt per
     * call.
     */
    public static function bootstrapParentServices(ApplicationContract $app): void
    {
        $app->getContainer()->getSingleton(RouteCollectionContract::class);
    }
}
