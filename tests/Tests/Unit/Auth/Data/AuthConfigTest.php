<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Auth\Data;

use Valkyrja\Auth\Authenticator\SessionAuthenticator;
use Valkyrja\Auth\Data\AuthConfig;
use Valkyrja\Auth\Data\Contract\AuthConfigContract;
use Valkyrja\Auth\Entity\User;
use Valkyrja\Auth\Store\NullStore;
use Valkyrja\Auth\Store\OrmStore;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class AuthConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(AuthConfigContract::class, new AuthConfig());
    }

    public function testDefaults(): void
    {
        $config = new AuthConfig();

        self::assertSame(SessionAuthenticator::class, $config->defaultAuthenticator);
        self::assertSame(OrmStore::class, $config->defaultStore);
        self::assertSame(User::class, $config->defaultUserEntity);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new AuthConfig(
            defaultStore: NullStore::class,
        );

        self::assertSame(NullStore::class, $config->defaultStore);
    }
}
