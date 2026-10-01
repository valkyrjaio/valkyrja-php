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

use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Session\Data\Contract\SessionLogConfigContract;

class SessionLogConfig implements SessionLogConfigContract
{
    /**
     * @param class-string<LoggerContract> $sessionLogLogger The logger to write to
     */
    public function __construct(
        public readonly string $sessionLogLogger = LoggerContract::class,
    ) {
    }
}
