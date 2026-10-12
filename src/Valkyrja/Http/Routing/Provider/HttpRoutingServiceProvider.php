<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Attribute\Provider\AttributeServiceProvider;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Http\Message\Response\Factory\Contract\ResponseFactoryContract;
use Valkyrja\Http\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Http\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Http\Routing\Collection\RouteCollection;
use Valkyrja\Http\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Http\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Http\Routing\Data\HttpRoutingData;
use Valkyrja\Http\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Http\Routing\Dispatcher\Router;
use Valkyrja\Http\Routing\Factory\Contract\RoutingResponseFactoryContract;
use Valkyrja\Http\Routing\Factory\RoutingResponseFactory;
use Valkyrja\Http\Routing\Matcher\Contract\MatcherContract;
use Valkyrja\Http\Routing\Matcher\Matcher;
use Valkyrja\Http\Routing\Processor\Contract\ProcessorContract;
use Valkyrja\Http\Routing\Processor\Processor;
use Valkyrja\Http\Routing\Url\Contract\UrlContract;
use Valkyrja\Http\Routing\Url\Url;
use Valkyrja\Reflection\Provider\ReflectionServiceProvider;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;

class HttpRoutingServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the router service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishRouter(ContainerContract $container): void
    {
        $exception         = $container->getSingleton(ThrowableCaughtHandlerContract::class);
        $routeMatched      = $container->getSingleton(RouteMatchedHandlerContract::class);
        $routeNotMatched   = $container->getSingleton(RouteNotMatchedHandlerContract::class);
        $routeDispatched   = $container->getSingleton(RouteDispatchedHandlerContract::class);
        $sendingResponse   = $container->getSingleton(SendingResponseHandlerContract::class);
        $responseSent      = $container->getSingleton(ResponseSentHandlerContract::class);

        $container->setSingleton(
            RouterContract::class,
            new Router(
                container: $container,
                matcher: $container->getSingleton(MatcherContract::class),
                responseFactory: $container->getSingleton(ResponseFactoryContract::class),
                throwableCaughtHandler: $exception,
                routeMatchedHandler: $routeMatched,
                routeNotMatchedHandler: $routeNotMatched,
                routeDispatchedHandler: $routeDispatched,
                sendingResponseHandler: $sendingResponse,
                responseSentHandler: $responseSent
            )
        );
    }

    /**
     * Publish the collection service.
     *
     * @param ContainerContract $container The container
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

        $data = $container->getSingleton(HttpRoutingData::class);

        $collection->setFromData($data);
    }

    /**
     * Publish the matcher service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishMatcher(ContainerContract $container): void
    {
        $container->setSingleton(
            MatcherContract::class,
            new Matcher(
                collection: $container->getSingleton(RouteCollectionContract::class),
                container: $container
            )
        );
    }

    /**
     * Publish the url service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishUrl(ContainerContract $container): void
    {
        $container->setSingleton(
            UrlContract::class,
            new Url(
                collection: $container->getSingleton(RouteCollectionContract::class),
            )
        );
    }

    /**
     * Publish the route attributes service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishAttributesRouteCollector(ContainerContract $container): void
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
                processor: $container->getSingleton(ProcessorContract::class)
            )
        );
    }

    /**
     * Publish the processor service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishProcessor(ContainerContract $container): void
    {
        $container->setSingleton(
            ProcessorContract::class,
            new Processor()
        );
    }

    /**
     * Publish the processor service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishResponseFactory(ContainerContract $container): void
    {
        $container->setSingleton(
            RoutingResponseFactoryContract::class,
            new RoutingResponseFactory(
                responseFactory: $container->getSingleton(ResponseFactoryContract::class),
                url: $container->getSingleton(UrlContract::class),
            )
        );
    }

    /**
     * Publish the data service.
     */
    public static function publishData(ContainerContract $container): void
    {
        $collection  = $container->getSingleton(RouteCollectionContract::class);
        $application = $container->getSingleton(ApplicationContract::class);

        $processor = $container->getSingleton(ProcessorContract::class);

        $providers = $application->getHttpProviders();

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
            // Iterate through the routes
            foreach ($collector->getRoutes(...$controllers) as $route) {
                // Add the route
                $collection->add($route);
            }
        }

        foreach ($routes as $route) {
            $collection->add(
                $processor->route($route)
            );
        }

        $container->setSingleton(HttpRoutingData::class, $collection->getData());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            RouterContract::class                 => [self::class, 'publishRouter'],
            RouteCollectionContract::class        => [self::class, 'publishRouteCollection'],
            MatcherContract::class                => [self::class, 'publishMatcher'],
            UrlContract::class                    => [self::class, 'publishUrl'],
            RouteCollectorContract::class         => [self::class, 'publishAttributesRouteCollector'],
            ProcessorContract::class              => [self::class, 'publishProcessor'],
            RoutingResponseFactoryContract::class => [self::class, 'publishResponseFactory'],
            HttpRoutingData::class                => [self::class, 'publishData'],
        ];
    }
}
