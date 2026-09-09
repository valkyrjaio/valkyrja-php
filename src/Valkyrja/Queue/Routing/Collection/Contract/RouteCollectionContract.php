<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Collection\Contract;

use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\QueueRoutingData;

interface RouteCollectionContract
{
    /**
     * Get a data representation of the collection.
     */
    public function getData(): QueueRoutingData;

    /**
     * Set data from a data object.
     */
    public function setFromData(QueueRoutingData $data): void;

    /**
     * Add routes, keyed by job name.
     */
    public function add(RouteContract ...$routes): static;

    /**
     * Get a route by job name.
     *
     * @param non-empty-string $name The job name
     */
    public function get(string $name): RouteContract;

    /**
     * Determine whether a route exists for a job name.
     *
     * @param non-empty-string $name The job name
     */
    public function has(string $name): bool;

    /**
     * Get all the routes.
     *
     * @return array<string, RouteContract>
     */
    public function all(): array;
}
