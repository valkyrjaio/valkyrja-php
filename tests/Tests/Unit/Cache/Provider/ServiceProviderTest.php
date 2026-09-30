<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cache\Provider;

use PHPUnit\Framework\MockObject\Exception;
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
use Valkyrja\Cache\Provider\CacheServiceProvider;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Cache\Data\CacheConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = CacheServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(CacheConfigContract::class, new CacheServiceProvider()->publishers());
        self::assertArrayHasKey(CacheRedisConfigContract::class, new CacheServiceProvider()->publishers());
        self::assertArrayHasKey(CacheLogConfigContract::class, new CacheServiceProvider()->publishers());
        self::assertArrayHasKey(CacheNullConfigContract::class, new CacheServiceProvider()->publishers());
        self::assertArrayHasKey(CacheContract::class, new CacheServiceProvider()->publishers());
        self::assertArrayHasKey(RedisCache::class, new CacheServiceProvider()->publishers());
        self::assertArrayHasKey(Client::class, new CacheServiceProvider()->publishers());
        self::assertArrayHasKey(LogCache::class, new CacheServiceProvider()->publishers());
        self::assertArrayHasKey(NullCache::class, new CacheServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $callback = new CacheServiceProvider()->publishers()[CacheConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CacheConfigContract::class, $config = $this->container->getSingleton(CacheConfigContract::class));
        self::assertSame(RedisCache::class, $config->defaultCache);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new CacheConfigFixture());

        $callback = new CacheServiceProvider()->publishers()[CacheConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CacheConfigContract::class, $config = $this->container->getSingleton(CacheConfigContract::class));
        self::assertSame(NullCache::class, $config->defaultCache);
    }

    public function testPublishRedisConfig(): void
    {
        $callback = new CacheServiceProvider()->publishers()[CacheRedisConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CacheRedisConfigContract::class, $config = $this->container->getSingleton(CacheRedisConfigContract::class));
        self::assertSame('127.0.0.1', $config->redisHost);
        self::assertSame(6379, $config->redisPort);
    }

    public function testPublishRedisConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new CacheConfigFixture());

        $callback = new CacheServiceProvider()->publishers()[CacheRedisConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CacheRedisConfigContract::class, $config = $this->container->getSingleton(CacheRedisConfigContract::class));
        self::assertSame('redis.test', $config->redisHost);
        self::assertSame(6380, $config->redisPort);
    }

    public function testPublishLogConfig(): void
    {
        $callback = new CacheServiceProvider()->publishers()[CacheLogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CacheLogConfigContract::class, $config = $this->container->getSingleton(CacheLogConfigContract::class));
        self::assertSame('', $config->cacheLogPrefix);
    }

    public function testPublishLogConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new CacheConfigFixture());

        $callback = new CacheServiceProvider()->publishers()[CacheLogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CacheLogConfigContract::class, $config = $this->container->getSingleton(CacheLogConfigContract::class));
        self::assertSame('log:', $config->cacheLogPrefix);
    }

    public function testPublishNullConfig(): void
    {
        $callback = new CacheServiceProvider()->publishers()[CacheNullConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CacheNullConfigContract::class, $config = $this->container->getSingleton(CacheNullConfigContract::class));
        self::assertSame('', $config->nullPrefix);
    }

    public function testPublishNullConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new CacheConfigFixture());

        $callback = new CacheServiceProvider()->publishers()[CacheNullConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CacheNullConfigContract::class, $config = $this->container->getSingleton(CacheNullConfigContract::class));
        self::assertSame('null:', $config->nullPrefix);
    }

    /**
     * @throws Exception
     */
    public function testPublishCache(): void
    {
        $this->container->setSingleton(CacheConfigContract::class, new CacheConfig());
        $this->container->setSingleton(RedisCache::class, self::createStub(RedisCache::class));

        $callback = new CacheServiceProvider()->publishers()[CacheContract::class];
        $callback($this->container);

        self::assertInstanceOf(RedisCache::class, $this->container->getSingleton(CacheContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishCacheWithConfiguredDefault(): void
    {
        $this->container->setSingleton(CacheConfigContract::class, new CacheConfig(defaultCache: NullCache::class));
        $this->container->setSingleton(NullCache::class, self::createStub(NullCache::class));

        $callback = new CacheServiceProvider()->publishers()[CacheContract::class];
        $callback($this->container);

        self::assertInstanceOf(NullCache::class, $this->container->getSingleton(CacheContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishRedisCache(): void
    {
        $this->container->setSingleton(CacheRedisConfigContract::class, new CacheRedisConfig(redisPrefix: 'redis:'));
        $this->container->setSingleton(Client::class, self::createStub(Client::class));

        $callback = new CacheServiceProvider()->publishers()[RedisCache::class];
        $callback($this->container);

        self::assertInstanceOf(RedisCache::class, $this->container->getSingleton(RedisCache::class));
    }

    public function testPublishRedisClient(): void
    {
        $this->container->setSingleton(
            CacheRedisConfigContract::class,
            new CacheRedisConfig(redisHost: 'redis.test', redisPort: 6380)
        );

        $callback = new CacheServiceProvider()->publishers()[Client::class];
        $callback($this->container);

        self::assertInstanceOf(Client::class, $this->container->getSingleton(Client::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishLogCache(): void
    {
        $this->container->setSingleton(CacheLogConfigContract::class, new CacheLogConfig(cacheLogPrefix: 'log:'));
        $this->container->setSingleton(LoggerContract::class, self::createStub(LoggerContract::class));

        $callback = new CacheServiceProvider()->publishers()[LogCache::class];
        $callback($this->container);

        self::assertInstanceOf(LogCache::class, $this->container->getSingleton(LogCache::class));
    }

    public function testPublishNullCache(): void
    {
        $this->container->setSingleton(CacheNullConfigContract::class, new CacheNullConfig(nullPrefix: 'null:'));

        $callback = new CacheServiceProvider()->publishers()[NullCache::class];
        $callback($this->container);

        self::assertInstanceOf(NullCache::class, $this->container->getSingleton(NullCache::class));
    }
}
