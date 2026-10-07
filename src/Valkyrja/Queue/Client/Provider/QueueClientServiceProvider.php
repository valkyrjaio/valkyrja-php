<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Provider;

use Override;
use Predis\Client;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDeferredClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSyncClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueClientConfig;
use Valkyrja\Queue\Client\Data\QueueRedisClientConfig;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DeferredClient;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientConfigNotFoundException;

use function sprintf;

class QueueClientServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the queue client config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof QueueClientConfigContract) {
            $container->setSingleton(QueueClientConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            QueueClientConfigContract::class,
            new QueueClientConfig()
        );
    }

    /**
     * Publish the sync client config service.
     *
     * @throws QueueClientConfigNotFoundException
     */
    public static function publishSyncConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        // The config names the entry of the application, so the framework has
        // no default to fall back to
        if (! $config instanceof QueueSyncClientConfigContract) {
            throw static::getConfigNotFoundException(QueueSyncClientConfigContract::class);
        }

        $container->setSingleton(QueueSyncClientConfigContract::class, $config);
    }

    /**
     * Publish the deferred client config service.
     *
     * @throws QueueClientConfigNotFoundException
     */
    public static function publishDeferredConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        // The config names the entry of the application, so the framework has
        // no default to fall back to
        if (! $config instanceof QueueDeferredClientConfigContract) {
            throw static::getConfigNotFoundException(QueueDeferredClientConfigContract::class);
        }

        $container->setSingleton(QueueDeferredClientConfigContract::class, $config);
    }

    /**
     * Publish the redis client config service.
     */
    public static function publishRedisConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof QueueRedisClientConfigContract) {
            $container->setSingleton(QueueRedisClientConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            QueueRedisClientConfigContract::class,
            new QueueRedisClientConfig()
        );
    }

    /**
     * Publish the client service.
     */
    public static function publishClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueClientConfigContract::class);

        $container->setSingleton(
            ClientContract::class,
            $container->getSingleton($config->defaultQueueClient)
        );
    }

    /**
     * Publish the sync client service.
     */
    public static function publishSyncClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueSyncClientConfigContract::class);

        $container->setSingleton(
            SyncClient::class,
            new SyncClient(
                entry: $config->syncEntry,
                applicationName: $container->getSingleton(ConfigContract::class)->applicationName,
            )
        );
    }

    /**
     * Publish the deferred client service.
     */
    public static function publishDeferredClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueDeferredClientConfigContract::class);

        $container->setSingleton(
            DeferredClient::class,
            new DeferredClient(
                entry: $config->deferredEntry,
                applicationName: $container->getSingleton(ConfigContract::class)->applicationName,
            )
        );
    }

    /**
     * Publish the in-memory client service.
     */
    public static function publishInMemoryClient(ContainerContract $container): void
    {
        $container->setSingleton(
            InMemoryClient::class,
            new InMemoryClient(
                applicationName: $container->getSingleton(ConfigContract::class)->applicationName,
            )
        );
    }

    /**
     * Publish the redis client service.
     */
    public static function publishRedisClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueRedisClientConfigContract::class);

        $container->setSingleton(
            RedisClient::class,
            new RedisClient(
                redis: new Client(
                    parameters: [
                        'host' => $config->redisHost,
                        'port' => $config->redisPort,
                    ]
                ),
                queue: $config->redisQueue,
                applicationName: $container->getSingleton(ConfigContract::class)->applicationName,
            )
        );
    }

    /**
     * Get the exception for an application config that does not implement a contract.
     *
     * @param class-string $contract The contract the application config must implement
     */
    protected static function getConfigNotFoundException(string $contract): QueueClientConfigNotFoundException
    {
        return new QueueClientConfigNotFoundException(
            sprintf('The application config does not implement %s.', $contract)
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            QueueClientConfigContract::class         => [self::class, 'publishConfig'],
            QueueSyncClientConfigContract::class     => [self::class, 'publishSyncConfig'],
            QueueDeferredClientConfigContract::class => [self::class, 'publishDeferredConfig'],
            QueueRedisClientConfigContract::class    => [self::class, 'publishRedisConfig'],
            ClientContract::class                    => [self::class, 'publishClient'],
            SyncClient::class                        => [self::class, 'publishSyncClient'],
            DeferredClient::class                    => [self::class, 'publishDeferredClient'],
            InMemoryClient::class                    => [self::class, 'publishInMemoryClient'],
            RedisClient::class                       => [self::class, 'publishRedisClient'],
        ];
    }
}
