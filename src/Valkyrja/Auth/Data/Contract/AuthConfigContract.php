<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Auth\Data\Contract;

use Valkyrja\Auth\Authenticator\Contract\AuthenticatorContract;
use Valkyrja\Auth\Entity\Contract\UserContract;
use Valkyrja\Auth\Store\Contract\StoreContract;

interface AuthConfigContract
{
    /** @var class-string<AuthenticatorContract> */
    public string $defaultAuthenticator {
        get;
    }

    /** @var class-string<StoreContract> */
    public string $defaultStore {
        get;
    }

    /** @var class-string<UserContract> */
    public string $defaultUserEntity {
        get;
    }
}
