<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Mail\Data;

use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Mail\Data\Contract\MailLogConfigContract;

class MailLogConfig implements MailLogConfigContract
{
    /**
     * @param class-string<LoggerContract> $mailLogLogger The logger to write to
     */
    public function __construct(
        public readonly string $mailLogLogger = LoggerContract::class,
    ) {
    }
}
