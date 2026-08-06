<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Data;

use Closure;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * The cached service map generated ahead of time (by Sindri) from the gRPC service controllers,
 * keyed by fully-qualified method name.
 *
 * Parallels HTTP's HttpRoutingData and CLI's CliRoutingData.
 */
readonly class GrpcRoutingData
{
    /**
     * @param array<string, Closure():RouteContract> $routes The routes
     */
    public function __construct(
        public array $routes = []
    ) {
    }
}
