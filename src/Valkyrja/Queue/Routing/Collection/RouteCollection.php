<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Collection;

use Closure;
use Override;
use Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\QueueRoutingData;
use Valkyrja\Queue\Routing\Throwable\Exception\QueueRoutingInvalidRouteNameException;

use function array_map;

class RouteCollection implements RouteCollectionContract
{
    /** @var array<string, Closure():RouteContract> */
    protected array $routes = [];

    /**
     * @inheritDoc
     */
    #[Override]
    public function getData(): QueueRoutingData
    {
        return new QueueRoutingData(
            routes: $this->routes,
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setFromData(QueueRoutingData $data): void
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
            $this->routes[$route->getName()] = static fn (): RouteContract => $route;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(string $name): RouteContract
    {
        $route = $this->routes[$name]
            ?? null;

        if ($route !== null) {
            return $this->ensureRoute($route);
        }

        throw new QueueRoutingInvalidRouteNameException("The route `$name` was not found.");
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
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
