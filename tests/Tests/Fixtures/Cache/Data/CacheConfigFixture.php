<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Cache\Data;

use Valkyrja\Application\Data\Config;
use Valkyrja\Cache\Data\Contract\CacheConfigContract;
use Valkyrja\Cache\Data\Contract\CacheLogConfigContract;
use Valkyrja\Cache\Data\Contract\CacheNullConfigContract;
use Valkyrja\Cache\Data\Contract\CacheRedisConfigContract;
use Valkyrja\Cache\Manager\Contract\CacheContract;
use Valkyrja\Cache\Manager\NullCache;
use Valkyrja\Log\Logger\Contract\LoggerContract;

/**
 * An application config that implements every cache contract at once.
 */
final class CacheConfigFixture extends Config implements CacheConfigContract, CacheRedisConfigContract, CacheLogConfigContract, CacheNullConfigContract
{
    /**
     * @param class-string<CacheContract>  $defaultCache
     * @param non-empty-string             $redisHost
     * @param class-string<LoggerContract> $cacheLogLogger
     */
    public function __construct(
        public string $defaultCache = NullCache::class,
        public string $redisHost = 'redis.test',
        public int $redisPort = 6380,
        public string $redisPrefix = 'redis:',
        public string $cacheLogLogger = LoggerContract::class,
        public string $cacheLogPrefix = 'log:',
        public string $nullPrefix = 'null:',
    ) {
        parent::__construct();
    }
}
