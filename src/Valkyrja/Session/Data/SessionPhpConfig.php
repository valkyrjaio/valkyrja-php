<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Session\Data;

use Valkyrja\Http\Message\Enum\SameSite;
use Valkyrja\Session\Data\Contract\SessionPhpConfigContract;

class SessionPhpConfig implements SessionPhpConfigContract
{
    /**
     * @param non-empty-string      $phpCookiePath     The path the cookie is valid for
     * @param non-empty-string|null $phpCookieDomain   The domain the cookie is valid for
     * @param int                   $phpCookieLifetime The lifetime of the cookie
     * @param bool                  $phpCookieSecure   Whether the cookie needs a secure connection
     * @param bool                  $phpCookieHttpOnly Whether to hide the cookie from JavaScript
     * @param SameSite              $phpCookieSameSite The same site policy of the cookie
     */
    public function __construct(
        public readonly string $phpCookiePath = '/',
        public readonly string|null $phpCookieDomain = null,
        public readonly int $phpCookieLifetime = 0,
        public readonly bool $phpCookieSecure = false,
        public readonly bool $phpCookieHttpOnly = false,
        public readonly SameSite $phpCookieSameSite = SameSite::NONE,
    ) {
    }
}
