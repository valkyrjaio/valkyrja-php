<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Session\Data;

use Valkyrja\Http\Message\Enum\SameSite;
use Valkyrja\Session\Data\Contract\SessionPhpConfigContract;
use Valkyrja\Session\Data\SessionPhpConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class SessionPhpConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(SessionPhpConfigContract::class, new SessionPhpConfig());
    }

    public function testDefaults(): void
    {
        $config = new SessionPhpConfig();

        self::assertSame('/', $config->phpCookiePath);
        self::assertNull($config->phpCookieDomain);
        self::assertSame(0, $config->phpCookieLifetime);
        self::assertFalse($config->phpCookieSecure);
        self::assertFalse($config->phpCookieHttpOnly);
        self::assertSame(SameSite::NONE, $config->phpCookieSameSite);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new SessionPhpConfig(
            phpCookiePath: '/test',
            phpCookieDomain: 'test.dev',
            phpCookieLifetime: 3600,
            phpCookieSecure: true,
            phpCookieHttpOnly: true,
            phpCookieSameSite: SameSite::STRICT,
        );

        self::assertSame('/test', $config->phpCookiePath);
        self::assertSame('test.dev', $config->phpCookieDomain);
        self::assertSame(3600, $config->phpCookieLifetime);
        self::assertTrue($config->phpCookieSecure);
        self::assertTrue($config->phpCookieHttpOnly);
        self::assertSame(SameSite::STRICT, $config->phpCookieSameSite);
    }
}
