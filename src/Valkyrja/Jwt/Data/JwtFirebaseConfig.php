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

use Valkyrja\Jwt\Data\Contract\JwtFirebaseConfigContract;
use Valkyrja\Jwt\Enum\Algorithm;

class JwtFirebaseConfig implements JwtFirebaseConfigContract
{
    /**
     * @param Algorithm $firebaseAlgorithm The algorithm that signs and verifies a token
     */
    public function __construct(
        public readonly Algorithm $firebaseAlgorithm = Algorithm::HS256,
    ) {
    }
}
