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

use Valkyrja\Jwt\Data\Contract\JwtConfigContract;
use Valkyrja\Jwt\Data\JwtConfig;
use Valkyrja\Jwt\Manager\FirebaseJwt;
use Valkyrja\Jwt\Manager\NullJwt;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class JwtConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(JwtConfigContract::class, new JwtConfig());
    }

    public function testDefaults(): void
    {
        $config = new JwtConfig();

        self::assertSame(FirebaseJwt::class, $config->defaultJwt);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new JwtConfig(defaultJwt: NullJwt::class);

        self::assertSame(NullJwt::class, $config->defaultJwt);
    }
}
