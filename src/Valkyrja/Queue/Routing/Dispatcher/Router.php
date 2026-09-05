<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Dispatcher;

use Override;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\SettlingResultHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Queue\Middleware\Handler\ResultSettledHandler;
use Valkyrja\Queue\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Queue\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Queue\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Queue\Middleware\Handler\SettlingResultHandler;
use Valkyrja\Queue\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Queue\Routing\Collection\RouteCollection;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract;

class Router implements RouterContract
{
    public function __construct(
        protected ContainerContract $container = new Container(),
        protected RouteCollectionContract $collection = new RouteCollection(),
        protected ThrowableCaughtHandlerContract $throwableCaughtHandler = new ThrowableCaughtHandler(),
        protected RouteMatchedHandlerContract $routeMatchedHandler = new RouteMatchedHandler(),
        protected RouteNotMatchedHandlerContract $routeNotMatchedHandler = new RouteNotMatchedHandler(),
        protected RouteDispatchedHandlerContract $routeDispatchedHandler = new RouteDispatchedHandler(),
        protected SettlingResultHandlerContract $settlingResultHandler = new SettlingResultHandler(),
        protected ResultSettledHandlerContract $resultSettledHandler = new ResultSettledHandler(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dispatch(JobContract $job): JobResult
    {
        $name = $job->getName();

        if (! $this->collection->has($name)) {
            // An unknown job name is the analog of gRPC's UNIMPLEMENTED: there
            // is no handler to retry into, so the default terminal fails it
            return $this->routeNotMatchedHandler->routeNotMatched(
                job: $job,
                result: JobResult::FAIL
            );
        }

        return $this->dispatchRoute(
            job: $job,
            route: $this->collection->get($name)
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dispatchRoute(JobContract $job, RouteContract $route): JobResult
    {
        $this->routeMatched($route);

        // Dispatch the RouteMatchedMiddleware
        $routeAfterMiddleware = $this->routeMatchedHandler->routeMatched(
            job: $job,
            route: $route
        );

        // If the return value after middleware is a result return it
        if ($routeAfterMiddleware instanceof JobResult) {
            return $routeAfterMiddleware;
        }

        // Set the route after middleware has potentially modified it in the service container
        $this->container->setSingleton(RouteContract::class, $routeAfterMiddleware);

        $handler = $routeAfterMiddleware->getHandler();
        $result  = $handler($this->container, $routeAfterMiddleware);

        return $this->routeDispatchedHandler->routeDispatched(
            job: $job,
            result: $result,
            route: $routeAfterMiddleware
        );
    }

    /**
     * Register the route's per-stage middleware and publish the route.
     */
    protected function routeMatched(RouteContract $route): void
    {
        $this->routeMatchedHandler->add(...$route->getRouteMatchedMiddleware());
        $this->routeDispatchedHandler->add(...$route->getRouteDispatchedMiddleware());
        $this->throwableCaughtHandler->add(...$route->getThrowableCaughtMiddleware());
        $this->settlingResultHandler->add(...$route->getSettlingResultMiddleware());
        $this->resultSettledHandler->add(...$route->getResultSettledMiddleware());

        // Set the found route in the service container
        $this->container->setSingleton(RouteContract::class, $route);
    }
}
