<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Data;

use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;

/**
 * A worker application that produces and consumes through a real redis server.
 */
final class RedisWorkerConfigFixture extends QueueConfig implements QueueClientConfigContract, QueueRedisClientConfigContract
{
    /** @var class-string<ClientContract> */
    public string $defaultQueueClient = RedisClient::class;

    /**
     * @param non-empty-string $redisHost       The host of the server under test
     * @param non-empty-string $redisQueue      The list key jobs are published to
     * @param non-empty-string $redisWorkerName The name of this worker's slot
     */
    public function __construct(
        public string $redisHost = '127.0.0.1',
        public int $redisPort = 6379,
        public string $redisQueue = 'valkyrja:tests:queue',
        public string $redisWorkerName = 'default',
    ) {
        parent::__construct(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
