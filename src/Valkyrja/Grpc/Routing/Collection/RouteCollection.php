<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Collection;

use Closure;
use Override;
use Valkyrja\Grpc\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Data\GrpcRoutingData;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingInvalidMethodException;

use function array_map;

class RouteCollection implements RouteCollectionContract
{
    /** @var array<string, Closure():RouteContract> */
    protected array $routes = [];

    /**
     * @inheritDoc
     */
    #[Override]
    public function getData(): GrpcRoutingData
    {
        return new GrpcRoutingData(
            routes: $this->routes,
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setFromData(GrpcRoutingData $data): void
    {
        $this->routes = $data->routes;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function add(RouteContract ...$routes): static
    {
        foreach ($routes as $route) {
            $this->routes[$route->getMethod()] = static fn (): RouteContract => $route;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(string $method): RouteContract
    {
        $route = $this->routes[$method]
            ?? null;

        if ($route !== null) {
            return $this->ensureRoute($route);
        }

        throw new GrpcRoutingInvalidMethodException("The route `$method` was not found.");
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $method): bool
    {
        return isset($this->routes[$method]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function all(): array
    {
        return array_map(
            fn (Closure $route): RouteContract => $this->ensureRoute($route),
            $this->routes
        );
    }

    /**
     * Ensure a route is returned.
     *
     * @param Closure():RouteContract $route The route
     */
    protected function ensureRoute(Closure $route): RouteContract
    {
        return $route();
    }
}
