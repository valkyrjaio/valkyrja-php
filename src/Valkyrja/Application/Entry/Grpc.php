<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry;

use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Application\Entry\Abstract\App;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Server\Handler\Contract\ServiceHandlerContract;

/**
 * gRPC entry point for single-call use — bootstraps the application and dispatches one call.
 *
 * Suitable for embedding or tests. For persistent server runtimes (RoadRunner, OpenSwoole, etc.)
 * use WorkerGrpc or one of its concrete subclasses instead, which bootstraps once and reuses the
 * frozen container per call.
 *
 * gRPC has no in-core, zero-dependency server the way HTTP does: gRPC mandates HTTP/2 with
 * trailers, so actually serving calls always requires an external transport.
 */
class Grpc extends App
{
    /**
     * Bootstrap the application from the given gRPC configuration.
     */
    public static function bootstrap(GrpcConfigContract $config): ApplicationContract
    {
        $app = static::start(
            config: $config,
        );

        static::bootstrapThrowableHandler($app, $app->getContainer());

        return $app;
    }

    /**
     * Bootstrap and handle a single call, returning the response after the full pipeline
     * (including ResponseSent).
     */
    public static function handle(GrpcConfigContract $config, ServiceCallContract $call): ServiceResponseContract
    {
        $app       = static::bootstrap($config);
        $container = $app->getContainer();

        $handler = $container->getSingleton(ServiceHandlerContract::class);

        $response = $handler->run($call);

        $handler->terminate($call, $response);

        return $response;
    }
}
