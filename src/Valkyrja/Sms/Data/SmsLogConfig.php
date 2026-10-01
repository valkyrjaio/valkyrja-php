<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Sms\Data;

use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Sms\Data\Contract\SmsLogConfigContract;

class SmsLogConfig implements SmsLogConfigContract
{
    /**
     * @param class-string<LoggerContract> $smsLogLogger The logger to write to
     */
    public function __construct(
        public readonly string $smsLogLogger = LoggerContract::class,
    ) {
    }
}
