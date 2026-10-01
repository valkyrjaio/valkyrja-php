<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Jwt\Data;

use Valkyrja\Jwt\Data\Contract\JwtConfigContract;
use Valkyrja\Jwt\Manager\Contract\JwtContract;
use Valkyrja\Jwt\Manager\FirebaseJwt;

class JwtConfig implements JwtConfigContract
{
    /**
     * @param class-string<JwtContract> $defaultJwt The jwt manager to use by default
     */
    public function __construct(
        public readonly string $defaultJwt = FirebaseJwt::class,
    ) {
    }
}
