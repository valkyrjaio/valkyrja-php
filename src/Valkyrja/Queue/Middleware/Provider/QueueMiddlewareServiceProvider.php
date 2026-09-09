<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Middleware\Provider;

use Override;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Queue\Middleware\Handler\Contract\JobReceivedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\SettlingResultHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Queue\Middleware\Handler\JobReceivedHandler;
use Valkyrja\Queue\Middleware\Handler\ResultSettledHandler;
use Valkyrja\Queue\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Queue\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Queue\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Queue\Middleware\Handler\SettlingResultHandler;
use Valkyrja\Queue\Middleware\Handler\ThrowableCaughtHandler;

class QueueMiddlewareServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the JobReceivedHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishJobReceivedHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueConfigContract::class);

        $middleware = $config->jobReceivedMiddleware;

        $container->setSingleton(
            JobReceivedHandlerContract::class,
            $handler = new JobReceivedHandler($container)
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
        $config = $container->getSingleton(QueueConfigContract::class);

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
        $config = $container->getSingleton(QueueConfigContract::class);

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
        $config = $container->getSingleton(QueueConfigContract::class);

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
        $config = $container->getSingleton(QueueConfigContract::class);

        $middleware = $config->throwableCaughtMiddleware;

        $container->setSingleton(
            ThrowableCaughtHandlerContract::class,
            $handler = new ThrowableCaughtHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * Publish the SettlingResultHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishSettlingResultHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueConfigContract::class);

        $middleware = $config->settlingResultMiddleware;

        $container->setSingleton(
            SettlingResultHandlerContract::class,
            $handler = new SettlingResultHandler($container)
        );

        $handler->add(...$middleware);
    }

    /**
     * Publish the ResultSettledHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishResultSettledHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueConfigContract::class);

        $middleware = $config->resultSettledMiddleware;

        $container->setSingleton(
            ResultSettledHandlerContract::class,
            $handler = new ResultSettledHandler($container)
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
            JobReceivedHandlerContract::class     => [self::class, 'publishJobReceivedHandler'],
            RouteMatchedHandlerContract::class    => [self::class, 'publishRouteMatchedHandler'],
            RouteNotMatchedHandlerContract::class => [self::class, 'publishRouteNotMatchedHandler'],
            RouteDispatchedHandlerContract::class => [self::class, 'publishRouteDispatchedHandler'],
            ThrowableCaughtHandlerContract::class => [self::class, 'publishThrowableCaughtHandler'],
            SettlingResultHandlerContract::class  => [self::class, 'publishSettlingResultHandler'],
            ResultSettledHandlerContract::class   => [self::class, 'publishResultSettledHandler'],
        ];
    }
}
