<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Client\Data;

use Valkyrja\Http\Client\Data\Contract\HttpClientLogConfigContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;

class HttpClientLogConfig implements HttpClientLogConfigContract
{
    /**
     * @param class-string<LoggerContract> $httpClientLogLogger The logger to write to
     */
    public function __construct(
        public readonly string $httpClientLogLogger = LoggerContract::class,
    ) {
    }
}
