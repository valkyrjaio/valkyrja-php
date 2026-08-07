<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Middleware\Provider;

use Override;
use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
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

class GrpcMiddlewareServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the CallReceivedHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishCallReceivedHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(GrpcConfigContract::class);

        $middleware = $config->callReceivedMiddleware;

        $container->setSingleton(
            CallReceivedHandlerContract::class,
            $handler = new CallReceivedHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * Publish the RouteMatchedHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishRouteMatchedHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(GrpcConfigContract::class);

        $middleware = $config->routeMatchedMiddleware;

        $container->setSingleton(
            RouteMatchedHandlerContract::class,
            $handler = new RouteMatchedHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * Publish the RouteNotMatchedHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishRouteNotMatchedHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(GrpcConfigContract::class);

        $middleware = $config->routeNotMatchedMiddleware;

        $container->setSingleton(
            RouteNotMatchedHandlerContract::class,
            $handler = new RouteNotMatchedHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * Publish the RouteDispatchedHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishRouteDispatchedHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(GrpcConfigContract::class);

        $middleware = $config->routeDispatchedMiddleware;

        $container->setSingleton(
            RouteDispatchedHandlerContract::class,
            $handler = new RouteDispatchedHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * Publish the ThrowableCaughtHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishThrowableCaughtHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(GrpcConfigContract::class);

        $middleware = $config->throwableCaughtMiddleware;

        $container->setSingleton(
            ThrowableCaughtHandlerContract::class,
            $handler = new ThrowableCaughtHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * Publish the SendingResponseHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishSendingResponseHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(GrpcConfigContract::class);

        $middleware = $config->sendingResponseMiddleware;

        $container->setSingleton(
            SendingResponseHandlerContract::class,
            $handler = new SendingResponseHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * Publish the ResponseSentHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishResponseSentHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(GrpcConfigContract::class);

        $middleware = $config->responseSentMiddleware;

        $container->setSingleton(
            ResponseSentHandlerContract::class,
            $handler = new ResponseSentHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            CallReceivedHandlerContract::class    => [self::class, 'publishCallReceivedHandler'],
            RouteMatchedHandlerContract::class    => [self::class, 'publishRouteMatchedHandler'],
            RouteNotMatchedHandlerContract::class => [self::class, 'publishRouteNotMatchedHandler'],
            RouteDispatchedHandlerContract::class => [self::class, 'publishRouteDispatchedHandler'],
            ThrowableCaughtHandlerContract::class => [self::class, 'publishThrowableCaughtHandler'],
            SendingResponseHandlerContract::class => [self::class, 'publishSendingResponseHandler'],
            ResponseSentHandlerContract::class    => [self::class, 'publishResponseSentHandler'],
        ];
    }
}
