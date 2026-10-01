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
use Valkyrja\Queue\Client\Data\Contract\QueueAmqpClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Manager\AmqpClient;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;

/**
 * A worker application that produces and consumes through a real AMQP broker.
 */
final class AmqpWorkerConfigFixture extends QueueConfig implements QueueClientConfigContract, QueueAmqpClientConfigContract
{
    /** @var class-string<ClientContract> */
    public string $defaultQueueClient = AmqpClient::class;

    /**
     * @param non-empty-string $amqpHost     The host of the broker under test
     * @param non-empty-string $amqpUser     The broker user
     * @param non-empty-string $amqpVhost    The broker virtual host
     * @param non-empty-string $amqpQueue    The queue jobs are published to
     * @param string           $amqpExchange The exchange jobs are published through; empty for the default
     */
    public function __construct(
        public string $amqpHost = '127.0.0.1',
        public int $amqpPort = 5672,
        public string $amqpUser = 'guest',
        public string $amqpPassword = 'guest',
        public string $amqpVhost = '/',
        public string $amqpQueue = 'valkyrja.tests.queue',
        public string $amqpExchange = '',
    ) {
        parent::__construct(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
