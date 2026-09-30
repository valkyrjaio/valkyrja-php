<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Auth\Data;

use Valkyrja\Auth\Constant\SessionItemId;
use Valkyrja\Auth\Data\Contract\AuthenticatedUsersContract;
use Valkyrja\Auth\Data\Contract\AuthSessionConfigContract;

class AuthSessionConfig implements AuthSessionConfigContract
{
    /**
     * @param non-empty-string                           $sessionItemId         The session item id to store the users under
     * @param class-string<AuthenticatedUsersContract>[] $sessionAllowedClasses The classes the session may deserialize
     */
    public function __construct(
        public readonly string $sessionItemId = SessionItemId::AUTHENTICATED_USERS,
        public readonly array $sessionAllowedClasses = [AuthenticatedUsers::class],
    ) {
    }
}
