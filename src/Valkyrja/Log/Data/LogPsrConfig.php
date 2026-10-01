<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Log\Data;

use Valkyrja\Log\Data\Contract\LogPsrConfigContract;
use Valkyrja\Log\Enum\LogLevel;

class LogPsrConfig implements LogPsrConfigContract
{
    /**
     * @param non-empty-string|null $psrName     The name of the log channel and the log file
     * @param non-empty-string|null $psrFilePath The directory to write the log file to
     * @param LogLevel              $psrLevel    The lowest level the logger writes
     */
    public function __construct(
        public readonly string|null $psrName = null,
        public readonly string|null $psrFilePath = null,
        public readonly LogLevel $psrLevel = LogLevel::DEBUG,
    ) {
    }
}
