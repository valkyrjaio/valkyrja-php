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

interface AuthSessionConfigContract
{
    /** @var non-empty-string */
    public string $sessionItemId {
        get;
    }

    /** @var class-string<AuthenticatedUsersContract>[] */
    public array $sessionAllowedClasses {
        get;
    }
}
