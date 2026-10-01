<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Jwt\Data;

use Valkyrja\Jwt\Data\Contract\JwtFirebaseConfigContract;
use Valkyrja\Jwt\Data\JwtFirebaseConfig;
use Valkyrja\Jwt\Enum\Algorithm;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class JwtFirebaseConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(JwtFirebaseConfigContract::class, new JwtFirebaseConfig());
    }

    public function testDefaults(): void
    {
        self::assertSame(Algorithm::HS256, new JwtFirebaseConfig()->firebaseAlgorithm);
    }

    public function testCustomValuesAreStored(): void
    {
        self::assertSame(Algorithm::RS256, new JwtFirebaseConfig(firebaseAlgorithm: Algorithm::RS256)->firebaseAlgorithm);
    }
}
