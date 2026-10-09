<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Attribute\Provider\AttributeServiceProvider;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ProcessExitingHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Cli\Routing\Caster\Caster;
use Valkyrja\Cli\Routing\Caster\Contract\CasterContract;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Collection\RouteCollection;
use Valkyrja\Cli\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Cli\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Cli\Routing\Data\CliRoutingData;
use Valkyrja\Cli\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Cli\Routing\Dispatcher\Router;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Reflection\Provider\ReflectionServiceProvider;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;

class CliRoutingServiceProvider implements ServiceProviderContract
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
     * Publish the caster service.
     */
    public static function publishCaster(ContainerContract $container): void
    {
        $container->setSingleton(
            CasterContract::class,
            new Caster(container: $container)
        );
    }

    /**
     * Publish the router service.
     */
    public static function publishRouter(ContainerContract $container): void
    {
        $throwableCaughtHandler           = $container->getSingleton(ThrowableCaughtHandlerContract::class);
        $commandMatchedHandler            = $container->getSingleton(RouteMatchedHandlerContract::class);
        $commandNotMatchedHandler         = $container->getSingleton(RouteNotMatchedHandlerContract::class);
        $commandDispatchedHandler         = $container->getSingleton(RouteDispatchedHandlerContract::class);
        $processExitingHandler            = $container->getSingleton(ProcessExitingHandlerContract::class);

        $container->setSingleton(
            RouterContract::class,
            new Router(
                container: $container,
                collection: $container->getSingleton(RouteCollectionContract::class),
                outputFactory: $container->getSingleton(OutputFactoryContract::class),
                throwableCaughtHandler: $throwableCaughtHandler,
                routeMatchedHandler: $commandMatchedHandler,
                routeNotMatchedHandler: $commandNotMatchedHandler,
                routeDispatchedHandler: $commandDispatchedHandler,
                processExitingHandler: $processExitingHandler
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

        $data = $container->getSingleton(CliRoutingData::class);

        $collection->setFromData($data);
    }

    /**
     * Publish the data service.
     */
    public static function publishData(ContainerContract $container): void
    {
        $collection  = $container->getSingleton(RouteCollectionContract::class);
        $application = $container->getSingleton(ApplicationContract::class);

        $providers = $application->getCliProviders();

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

            // Get all the attributes routes from the list of controllers
            $collection->add(
                ...$collector->getRoutes(...$controllers)
            );
        }

        $collection->add(...$routes);

        $container->setSingleton(CliRoutingData::class, $collection->getData());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            RouteCollectorContract::class  => [self::class, 'publishAttributeRouteCollector'],
            CasterContract::class          => [self::class, 'publishCaster'],
            RouterContract::class          => [self::class, 'publishRouter'],
            RouteCollectionContract::class => [self::class, 'publishRouteCollection'],
            CliRoutingData::class          => [self::class, 'publishData'],
        ];
    }
}
