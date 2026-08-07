<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Collection\Contract;

use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Data\GrpcRoutingData;

interface RouteCollectionContract
{
    /**
     * Get a data representation of the collection.
     */
    public function getData(): GrpcRoutingData;

    /**
     * Set data from a data object.
     */
    public function setFromData(GrpcRoutingData $data): void;

    /**
     * Add routes.
     *
     * @param RouteContract ...$routes The routes
     */
    public function add(RouteContract ...$routes): static;

    /**
     * Get a route by its fully-qualified method.
     *
     * @param string $method The fully-qualified method
     */
    public function get(string $method): RouteContract;

    /**
     * Determine whether a route exists for the fully-qualified method.
     *
     * @param string $method The fully-qualified method
     */
    public function has(string $method): bool;

    /**
     * Get all the routes.
     *
     * @return array<string, RouteContract>
     */
    public function all(): array;
}
