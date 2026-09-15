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
use Pheanstalk\Pheanstalk;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use Predis\Client;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\Queue\Client\Data\Contract\QueueAmqpClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueBeanstalkdClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDatabaseClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDeferredClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSqsClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSyncClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueAmqpClientConfig;
use Valkyrja\Queue\Client\Data\QueueBeanstalkdClientConfig;
use Valkyrja\Queue\Client\Data\QueueClientConfig;
use Valkyrja\Queue\Client\Data\QueueDatabaseClientConfig;
use Valkyrja\Queue\Client\Data\QueueRedisClientConfig;
use Valkyrja\Queue\Client\Data\QueueSqsClientConfig;
use Valkyrja\Queue\Client\Manager\AmqpClient;
use Valkyrja\Queue\Client\Manager\BeanstalkdClient;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DatabaseClient;
use Valkyrja\Queue\Client\Manager\DeferredClient;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Client\Manager\SqsClient;
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
     * Publish the AMQP client config service.
     */
    public static function publishAmqpConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof QueueAmqpClientConfigContract) {
            $container->setSingleton(QueueAmqpClientConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            QueueAmqpClientConfigContract::class,
            new QueueAmqpClientConfig()
        );
    }

    /**
     * Publish the SQS client config service.
     */
    public static function publishSqsConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof QueueSqsClientConfigContract) {
            $container->setSingleton(QueueSqsClientConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            QueueSqsClientConfigContract::class,
            new QueueSqsClientConfig()
        );
    }

    /**
     * Publish the beanstalkd client config service.
     */
    public static function publishBeanstalkdConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof QueueBeanstalkdClientConfigContract) {
            $container->setSingleton(QueueBeanstalkdClientConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            QueueBeanstalkdClientConfigContract::class,
            new QueueBeanstalkdClientConfig()
        );
    }

    /**
     * Publish the database client config service.
     */
    public static function publishDatabaseConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof QueueDatabaseClientConfigContract) {
            $container->setSingleton(QueueDatabaseClientConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            QueueDatabaseClientConfigContract::class,
            new QueueDatabaseClientConfig()
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
     * Publish the AMQP client service.
     */
    public static function publishAmqpClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueAmqpClientConfigContract::class);

        $container->setSingleton(
            AmqpClient::class,
            new AmqpClient(
                // A lazy connection waits for the first publish, so building the
                // client never reaches the broker
                connection: new AMQPLazyConnection(
                    $config->amqpHost,
                    $config->amqpPort,
                    $config->amqpUser,
                    $config->amqpPassword,
                    $config->amqpVhost,
                ),
                queue: $config->amqpQueue,
                exchange: $config->amqpExchange,
                applicationName: $container->getSingleton(ConfigContract::class)->applicationName,
            )
        );
    }

    /**
     * Publish the SQS client service.
     */
    public static function publishSqsClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueSqsClientConfigContract::class);

        $container->setSingleton(
            SqsClient::class,
            new SqsClient(
                sqs: SqsClient::createSqs($config),
                queueUrl: $config->sqsQueueUrl,
                applicationName: $container->getSingleton(ConfigContract::class)->applicationName,
            )
        );
    }

    /**
     * Publish the beanstalkd client service.
     */
    public static function publishBeanstalkdClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueBeanstalkdClientConfigContract::class);

        $container->setSingleton(
            BeanstalkdClient::class,
            new BeanstalkdClient(
                pheanstalk: Pheanstalk::create($config->beanstalkdHost, $config->beanstalkdPort),
                tube: $config->beanstalkdTube,
                timeToRelease: $config->beanstalkdTimeToRelease,
                applicationName: $container->getSingleton(ConfigContract::class)->applicationName,
            )
        );
    }

    /**
     * Publish the database client service.
     */
    public static function publishDatabaseClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueDatabaseClientConfigContract::class);

        $container->setSingleton(
            DatabaseClient::class,
            new DatabaseClient(
                manager: $container->getSingleton(ManagerContract::class),
                queue: $config->databaseQueue,
                table: $config->databaseTable,
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
            QueueClientConfigContract::class           => [self::class, 'publishConfig'],
            QueueSyncClientConfigContract::class       => [self::class, 'publishSyncConfig'],
            QueueDeferredClientConfigContract::class   => [self::class, 'publishDeferredConfig'],
            QueueRedisClientConfigContract::class      => [self::class, 'publishRedisConfig'],
            QueueAmqpClientConfigContract::class       => [self::class, 'publishAmqpConfig'],
            QueueSqsClientConfigContract::class        => [self::class, 'publishSqsConfig'],
            QueueBeanstalkdClientConfigContract::class => [self::class, 'publishBeanstalkdConfig'],
            QueueDatabaseClientConfigContract::class   => [self::class, 'publishDatabaseConfig'],
            ClientContract::class                      => [self::class, 'publishClient'],
            SyncClient::class                          => [self::class, 'publishSyncClient'],
            DeferredClient::class                      => [self::class, 'publishDeferredClient'],
            InMemoryClient::class                      => [self::class, 'publishInMemoryClient'],
            RedisClient::class                         => [self::class, 'publishRedisClient'],
            AmqpClient::class                          => [self::class, 'publishAmqpClient'],
            SqsClient::class                           => [self::class, 'publishSqsClient'],
            BeanstalkdClient::class                    => [self::class, 'publishBeanstalkdClient'],
            DatabaseClient::class                      => [self::class, 'publishDatabaseClient'],
        ];
    }
}
