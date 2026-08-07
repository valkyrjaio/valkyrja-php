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
