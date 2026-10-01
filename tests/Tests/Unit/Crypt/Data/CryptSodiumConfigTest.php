<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Crypt\Data;

use Valkyrja\Crypt\Data\Contract\CryptSodiumConfigContract;
use Valkyrja\Crypt\Data\CryptSodiumConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CryptSodiumConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(CryptSodiumConfigContract::class, new CryptSodiumConfig(sodiumKey: 'sodium_key'));
    }

    public function testCustomValuesAreStored(): void
    {
        self::assertSame('sodium_key', new CryptSodiumConfig(sodiumKey: 'sodium_key')->sodiumKey);
    }
}
