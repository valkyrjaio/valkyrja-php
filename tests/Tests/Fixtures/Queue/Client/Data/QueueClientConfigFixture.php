<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Client\Data;

use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Entry\Abstract\InternalQueue;
use Valkyrja\Queue\Client\Data\Contract\QueueAmqpClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDeferredClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSyncClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Tests\Fixtures\Application\Entry\InternalQueueFixture;

/**
 * An application config that implements every queue client config contract.
 */
final class QueueClientConfigFixture extends Config implements QueueClientConfigContract, QueueSyncClientConfigContract, QueueDeferredClientConfigContract, QueueRedisClientConfigContract, QueueAmqpClientConfigContract
{
    /**
     * @param class-string<ClientContract> $defaultQueueClient
     * @param class-string<InternalQueue>  $syncEntry
     * @param class-string<InternalQueue>  $deferredEntry
     * @param non-empty-string             $redisHost
     * @param non-empty-string             $redisQueue
     * @param non-empty-string             $amqpHost
     * @param non-empty-string             $amqpUser
     * @param non-empty-string             $amqpVhost
     * @param non-empty-string             $amqpQueue
     */
    public function __construct(
        public string $defaultQueueClient = SyncClient::class,
        public string $syncEntry = InternalQueueFixture::class,
        public string $deferredEntry = InternalQueueFixture::class,
        public string $redisHost = 'redis.test',
        public int $redisPort = 6380,
        public string $redisQueue = 'queues:test',
        public string $amqpHost = 'amqp.test',
        public int $amqpPort = 5673,
        public string $amqpUser = 'worker',
        public string $amqpPassword = 'secret',
        public string $amqpVhost = '/jobs',
        public string $amqpQueue = 'queues.test',
        public string $amqpExchange = 'jobs',
    ) {
        parent::__construct(
            applicationName: 'host',
        );
    }
}
