<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Crypt\Data;

use Valkyrja\Application\Data\Config;
use Valkyrja\Crypt\Data\Contract\CryptConfigContract;
use Valkyrja\Crypt\Data\Contract\CryptSodiumConfigContract;
use Valkyrja\Crypt\Manager\Contract\CryptContract;
use Valkyrja\Crypt\Manager\NullCrypt;

/**
 * An application config that implements every crypt contract at once.
 */
final class CryptConfigFixture extends Config implements CryptConfigContract, CryptSodiumConfigContract
{
    /**
     * @param class-string<CryptContract> $defaultCrypt
     * @param non-empty-string            $sodiumKey
     */
    public function __construct(
        public string $defaultCrypt = NullCrypt::class,
        public string $sodiumKey = 'sodium_fixture_key',
    ) {
        parent::__construct();
    }
}
