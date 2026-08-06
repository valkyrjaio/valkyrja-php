<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Attribute;

use Attribute;

/**
 * Marks a class as a gRPC service controller.
 *
 * The scan populates the service map from its Method-attributed methods, keyed by
 * `/service/methodName`.
 *
 * The attribute drops the `Grpc` prefix: it is controller-facing and lives in the protocol's own
 * attribute namespace, and a gRPC controller never imports the HTTP or CLI equivalents, so the
 * short name never collides.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Service
{
    /**
     * @param non-empty-string $service The fully-qualified service name, e.g. `package.Service`
     */
    public function __construct(
        public string $service,
    ) {
    }
}
