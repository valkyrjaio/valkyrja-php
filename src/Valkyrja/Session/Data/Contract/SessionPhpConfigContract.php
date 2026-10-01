<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Session\Data\Contract;

use Valkyrja\Http\Message\Enum\SameSite;

interface SessionPhpConfigContract
{
    /** @var non-empty-string */
    public string $phpCookiePath {
        get;
    }

    /** @var non-empty-string|null */
    public string|null $phpCookieDomain {
        get;
    }

    public int $phpCookieLifetime {
        get;
    }

    public bool $phpCookieSecure {
        get;
    }

    public bool $phpCookieHttpOnly {
        get;
    }

    public SameSite $phpCookieSameSite {
        get;
    }
}
