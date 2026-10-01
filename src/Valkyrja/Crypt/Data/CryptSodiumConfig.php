<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Crypt\Data;

use SensitiveParameter;
use Valkyrja\Crypt\Data\Contract\CryptSodiumConfigContract;

class CryptSodiumConfig implements CryptSodiumConfigContract
{
    /**
     * @param non-empty-string $sodiumKey The key that encrypts and decrypts a message
     */
    public function __construct(
        #[SensitiveParameter]
        public readonly string $sodiumKey,
    ) {
    }
}
