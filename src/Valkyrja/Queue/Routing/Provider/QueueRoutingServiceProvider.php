<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Attribute\Provider\AttributeServiceProvider;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\SettlingResultHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Queue\Routing\Collection\RouteCollection;
use Valkyrja\Queue\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Queue\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Queue\Routing\Data\QueueRoutingData;
use Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Queue\Routing\Dispatcher\Router;
use Valkyrja\Reflection\Provider\ReflectionServiceProvider;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;

class QueueRoutingServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the attribute collector service.
     */
    public static function publishAttributeRouteCollector(ContainerContract $container): void
    {
        if (! $container->isSingleton(ReflectorContract::class)) {
            ReflectionServiceProvider::publishReflection($container);
        }

        if (! $container->isSingleton(CollectorContract::class)) {
            AttributeServiceProvider::publishAttributes($container);
        }

        $container->setSingleton(
            RouteCollectorContract::class,
            new AttributeRouteCollector(
                attributes: $container->getSingleton(CollectorContract::class),
                reflection: $container->getSingleton(ReflectorContract::class),
            )
        );
    }

    /**
     * Publish the router service.
     */
    public static function publishRouter(ContainerContract $container): void
    {
        $container->setSingleton(
            RouterContract::class,
            new Router(
                container: $container,
                collection: $container->getSingleton(RouteCollectionContract::class),
                throwableCaughtHandler: $container->getSingleton(ThrowableCaughtHandlerContract::class),
                routeMatchedHandler: $container->getSingleton(RouteMatchedHandlerContract::class),
                routeNotMatchedHandler: $container->getSingleton(RouteNotMatchedHandlerContract::class),
                routeDispatchedHandler: $container->getSingleton(RouteDispatchedHandlerContract::class),
                settlingResultHandler: $container->getSingleton(SettlingResultHandlerContract::class),
                resultSettledHandler: $container->getSingleton(ResultSettledHandlerContract::class),
            )
        );
    }

    /**
     * Publish the collection service.
     */
    public static function publishRouteCollection(ContainerContract $container): void
    {
        $container->setSingleton(
            RouteCollectionContract::class,
            $collection = new RouteCollection()
        );

        $app = $container->getSingleton(ApplicationContract::class);

        if ($app->getDebugMode()) {
            self::publishData($container);

            return;
        }

        $data = $container->getSingleton(QueueRoutingData::class);

        $collection->setFromData($data);
    }

    /**
     * Publish the data service.
     */
    public static function publishData(ContainerContract $container): void
    {
        $collection  = $container->getSingleton(RouteCollectionContract::class);
        $application = $container->getSingleton(ApplicationContract::class);

        $providers = $application->getQueueProviders();

        $controllers = [];
        $routes      = [];

        foreach ($providers as $provider) {
            $controllers = [
                ...$controllers,
                ...$provider->getControllerClasses(),
            ];

            $routes = [
                ...$routes,
                ...$provider->getRoutes(),
            ];
        }

        if ($controllers !== []) {
            /** @var RouteCollectorContract $collector */
            $collector = $container->getSingleton(RouteCollectorContract::class);

            // Get all the attributed routes from the list of handler classes
            $collection->add(
                ...$collector->getRoutes(...$controllers)
            );
        }

        $collection->add(...$routes);

        $container->setSingleton(QueueRoutingData::class, $collection->getData());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            RouteCollectorContract::class  => [self::class, 'publishAttributeRouteCollector'],
            RouterContract::class          => [self::class, 'publishRouter'],
            RouteCollectionContract::class => [self::class, 'publishRouteCollection'],
            QueueRoutingData::class        => [self::class, 'publishData'],
        ];
    }
}
