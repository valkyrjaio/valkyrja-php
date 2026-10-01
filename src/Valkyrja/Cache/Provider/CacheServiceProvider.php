<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cache\Provider;

use Override;
use Predis\Client;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Cache\Data\CacheConfig;
use Valkyrja\Cache\Data\CacheLogConfig;
use Valkyrja\Cache\Data\CacheNullConfig;
use Valkyrja\Cache\Data\CacheRedisConfig;
use Valkyrja\Cache\Data\Contract\CacheConfigContract;
use Valkyrja\Cache\Data\Contract\CacheLogConfigContract;
use Valkyrja\Cache\Data\Contract\CacheNullConfigContract;
use Valkyrja\Cache\Data\Contract\CacheRedisConfigContract;
use Valkyrja\Cache\Manager\Contract\CacheContract;
use Valkyrja\Cache\Manager\LogCache;
use Valkyrja\Cache\Manager\NullCache;
use Valkyrja\Cache\Manager\RedisCache;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;

class CacheServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the cache config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CacheConfigContract) {
            $container->setSingleton(CacheConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            CacheConfigContract::class,
            new CacheConfig()
        );
    }

    /**
     * Publish the redis cache config service.
     */
    public static function publishRedisConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CacheRedisConfigContract) {
            $container->setSingleton(CacheRedisConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            CacheRedisConfigContract::class,
            new CacheRedisConfig()
        );
    }

    /**
     * Publish the log cache config service.
     */
    public static function publishLogConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CacheLogConfigContract) {
            $container->setSingleton(CacheLogConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            CacheLogConfigContract::class,
            new CacheLogConfig()
        );
    }

    /**
     * Publish the null cache config service.
     */
    public static function publishNullConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CacheNullConfigContract) {
            $container->setSingleton(CacheNullConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            CacheNullConfigContract::class,
            new CacheNullConfig()
        );
    }

    /**
     * Publish the cache service.
     */
    public static function publishCache(ContainerContract $container): void
    {
        $config = $container->getSingleton(CacheConfigContract::class);

        $container->setSingleton(
            CacheContract::class,
            $container->getSingleton($config->defaultCache)
        );
    }

    /**
     * Publish the redis cache service.
     */
    public static function publishRedisCache(ContainerContract $container): void
    {
        $config = $container->getSingleton(CacheRedisConfigContract::class);

        $container->setSingleton(
            RedisCache::class,
            new RedisCache(
                client: $container->getSingleton(Client::class),
                prefix: $config->redisPrefix
            )
        );
    }

    /**
     * Publish the redis client service.
     */
    public static function publishRedisClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(CacheRedisConfigContract::class);

        $container->setSingleton(
            Client::class,
            new Client(
                parameters: [
                    'host' => $config->redisHost,
                    'port' => $config->redisPort,
                ]
            )
        );
    }

    /**
     * Publish the log cache service.
     */
    public static function publishLogCache(ContainerContract $container): void
    {
        $config = $container->getSingleton(CacheLogConfigContract::class);

        $container->setSingleton(
            LogCache::class,
            new LogCache(
                logger: $container->getSingleton($config->cacheLogLogger),
                prefix: $config->cacheLogPrefix
            )
        );
    }

    /**
     * Publish the null cache service.
     */
    public static function publishNullCache(ContainerContract $container): void
    {
        $config = $container->getSingleton(CacheNullConfigContract::class);

        $container->setSingleton(
            NullCache::class,
            new NullCache(
                prefix: $config->nullPrefix
            )
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            CacheConfigContract::class      => [self::class, 'publishConfig'],
            CacheRedisConfigContract::class => [self::class, 'publishRedisConfig'],
            CacheLogConfigContract::class   => [self::class, 'publishLogConfig'],
            CacheNullConfigContract::class  => [self::class, 'publishNullConfig'],
            CacheContract::class            => [self::class, 'publishCache'],
            RedisCache::class               => [self::class, 'publishRedisCache'],
            Client::class                   => [self::class, 'publishRedisClient'],
            LogCache::class                 => [self::class, 'publishLogCache'],
            NullCache::class                => [self::class, 'publishNullCache'],
        ];
    }
}
