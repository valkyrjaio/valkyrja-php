<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cache\Data;

use Valkyrja\Cache\Data\Contract\CacheLogConfigContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;

class CacheLogConfig implements CacheLogConfigContract
{
    /**
     * @param class-string<LoggerContract> $cacheLogLogger The logger to write to
     * @param string                       $cacheLogPrefix      The prefix to prepend to every key
     */
    public function __construct(
        public readonly string $cacheLogLogger = LoggerContract::class,
        public readonly string $cacheLogPrefix = '',
    ) {
    }
}
