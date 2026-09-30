<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Client\Data\Contract;

use Valkyrja\Log\Logger\Contract\LoggerContract;

interface HttpClientLogConfigContract
{
    /** @var class-string<LoggerContract> */
    public string $httpClientLogLogger {
        get;
    }
}
