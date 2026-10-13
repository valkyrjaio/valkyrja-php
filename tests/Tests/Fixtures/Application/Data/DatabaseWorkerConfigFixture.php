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
use Valkyrja\Application\Entry\Database\DatabaseQueue;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDatabaseClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DatabaseClient;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;

/**
 * A worker application that produces and consumes through a real database table.
 */
final class DatabaseWorkerConfigFixture extends QueueConfig implements QueueClientConfigContract, QueueDatabaseClientConfigContract
{
    /** @var class-string<ClientContract> */
    public string $defaultQueueClient = DatabaseClient::class;

    /**
     * @param non-empty-string $queueDatabaseClientQueue                The queue jobs are published to
     * @param non-empty-string $queueDatabaseClientTable                The table jobs are stored in
     * @param int<1, max>      $queueDatabaseClientReservationTimeoutMs The milliseconds a claim holds a row
     */
    public function __construct(
        public string $queueDatabaseClientQueue = 'default',
        public string $queueDatabaseClientTable = DatabaseClient::DEFAULT_TABLE,
        public int $queueDatabaseClientReservationTimeoutMs = DatabaseQueue::DEFAULT_RESERVATION_TIMEOUT_MS,
    ) {
        parent::__construct(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
