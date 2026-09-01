<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Attribute\Provider\AttributeServiceProvider;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Grpc\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Grpc\Routing\Collection\RouteCollection;
use Valkyrja\Grpc\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Grpc\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Grpc\Routing\Data\GrpcRoutingData;
use Valkyrja\Grpc\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Grpc\Routing\Dispatcher\Router;
use Valkyrja\Reflection\Provider\ReflectionServiceProvider;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;

class GrpcRoutingServiceProvider implements ServiceProviderContract
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
                routeMatchedHandler: $container->getSingleton(RouteMatchedHandlerContract::class),
                routeNotMatchedHandler: $container->getSingleton(RouteNotMatchedHandlerContract::class),
                routeDispatchedHandler: $container->getSingleton(RouteDispatchedHandlerContract::class),
                throwableCaughtHandler: $container->getSingleton(ThrowableCaughtHandlerContract::class),
                sendingResponseHandler: $container->getSingleton(SendingResponseHandlerContract::class),
                responseSentHandler: $container->getSingleton(ResponseSentHandlerContract::class),
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

        $data = $container->getSingleton(GrpcRoutingData::class);

        $collection->setFromData($data);
    }

    /**
     * Publish the data service.
     */
    public static function publishData(ContainerContract $container): void
    {
        $collection  = $container->getSingleton(RouteCollectionContract::class);
        $application = $container->getSingleton(ApplicationContract::class);

        $providers = $application->getGrpcProviders();

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

            // Get all the attributed routes from the list of controllers
            $collection->add(
                ...$collector->getRoutes(...$controllers)
            );
        }

        $collection->add(...$routes);

        $container->setSingleton(GrpcRoutingData::class, $collection->getData());
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
            GrpcRoutingData::class         => [self::class, 'publishData'],
        ];
    }
}
