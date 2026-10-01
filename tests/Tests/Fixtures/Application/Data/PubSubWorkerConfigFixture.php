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
use Valkyrja\Queue\Client\Data\Contract\QueuePubSubClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\PubSubClient;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;

/**
 * A worker application that produces and consumes through a Pub/Sub emulator.
 */
final class PubSubWorkerConfigFixture extends QueueConfig implements QueueClientConfigContract, QueuePubSubClientConfigContract
{
    /** @var class-string<ClientContract> */
    public string $defaultQueueClient = PubSubClient::class;

    /**
     * @param non-empty-string $pubSubProjectId The project of the emulator under test
     * @param non-empty-string $pubSubTopic     The topic jobs are published to
     */
    public function __construct(
        public string $pubSubProjectId = 'valkyrja-tests',
        public string $pubSubTopic = 'jobs',
    ) {
        parent::__construct(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
