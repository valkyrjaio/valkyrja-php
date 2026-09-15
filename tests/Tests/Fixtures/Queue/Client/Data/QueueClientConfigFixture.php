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
use Valkyrja\Queue\Client\Data\Contract\QueueBeanstalkdClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDatabaseClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDeferredClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueuePubSubClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSqsClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSyncClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Tests\Fixtures\Application\Entry\InternalQueueFixture;

/**
 * An application config that implements every queue client config contract.
 */
final class QueueClientConfigFixture extends Config implements QueueClientConfigContract, QueueSyncClientConfigContract, QueueDeferredClientConfigContract, QueueRedisClientConfigContract, QueueAmqpClientConfigContract, QueueSqsClientConfigContract, QueueBeanstalkdClientConfigContract, QueueDatabaseClientConfigContract, QueuePubSubClientConfigContract
{
    /**
     * @param class-string<ClientContract> $defaultQueueClient
     * @param class-string<InternalQueue>  $syncEntry
     * @param class-string<InternalQueue>  $deferredEntry
     * @param non-empty-string             $queueRedisClientHost
     * @param non-empty-string             $queueRedisClientQueue
     * @param non-empty-string             $queueRedisClientWorkerName
     * @param non-empty-string             $queueAmqpClientHost
     * @param non-empty-string             $queueAmqpClientUser
     * @param non-empty-string             $queueAmqpClientVhost
     * @param non-empty-string             $queueAmqpClientQueue
     * @param non-empty-string             $queueSqsClientRegion
     * @param non-empty-string|null        $queueSqsClientEndpoint
     * @param non-empty-string|null        $queueSqsClientAccessKeyId
     * @param non-empty-string|null        $queueSqsClientAccessKeySecret
     * @param non-empty-string             $queueSqsClientQueueUrl
     * @param int<1, 20>                   $queueSqsClientWaitTimeSeconds
     * @param non-empty-string             $queueBeanstalkdClientHost
     * @param non-empty-string             $queueBeanstalkdClientTube
     * @param int<0, max>                  $queueBeanstalkdClientTimeToRelease
     * @param int<1, max>                  $queueBeanstalkdClientReserveTimeout
     * @param non-empty-string             $queueDatabaseClientQueue
     * @param non-empty-string             $queueDatabaseClientTable
     * @param non-empty-string             $pubSubProjectId
     * @param non-empty-string             $pubSubTopic
     */
    public function __construct(
        public string $defaultQueueClient = SyncClient::class,
        public string $syncEntry = InternalQueueFixture::class,
        public string $deferredEntry = InternalQueueFixture::class,
        public string $queueRedisClientHost = 'redis.test',
        public int $queueRedisClientPort = 6380,
        public string $queueRedisClientPrefix = '',
        public string $queueRedisClientQueue = 'queues:test',
        public string $queueRedisClientWorkerName = 'worker.test',
        public string $queueAmqpClientHost = 'amqp.test',
        public int $queueAmqpClientPort = 5673,
        public string $queueAmqpClientUser = 'worker',
        public string $queueAmqpClientPassword = 'secret',
        public string $queueAmqpClientVhost = '/jobs',
        public string $queueAmqpClientQueue = 'queues.test',
        public string $queueAmqpClientExchange = 'jobs',
        public string $queueSqsClientRegion = 'eu-west-1',
        public string|null $queueSqsClientEndpoint = 'http://sqs.test',
        public string|null $queueSqsClientAccessKeyId = 'key',
        public string|null $queueSqsClientAccessKeySecret = 'secret',
        public string $queueSqsClientQueueUrl = 'http://sqs.test/000000000000/test',
        public int $queueSqsClientWaitTimeSeconds = 20,
        public string $queueBeanstalkdClientHost = 'beanstalkd.test',
        public int $queueBeanstalkdClientPort = 11301,
        public string $queueBeanstalkdClientTube = 'jobs',
        public int $queueBeanstalkdClientTimeToRelease = 90,
        public int $queueBeanstalkdClientReserveTimeout = 3,
        public string $queueDatabaseClientQueue = 'jobs',
        public string $queueDatabaseClientTable = 'jobs_test',
        public int $queueDatabaseClientReservationTimeoutMs = 60_000,
        public string $pubSubProjectId = 'valkyrja-tests',
        public string $pubSubTopic = 'jobs',
    ) {
        parent::__construct(
            applicationName: 'host',
        );
    }
}
