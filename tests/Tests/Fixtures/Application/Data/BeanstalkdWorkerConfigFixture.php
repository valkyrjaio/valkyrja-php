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
use Valkyrja\Queue\Client\Data\Contract\QueueBeanstalkdClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Manager\BeanstalkdClient;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;

/**
 * A worker application that produces and consumes through a real beanstalkd server.
 */
final class BeanstalkdWorkerConfigFixture extends QueueConfig implements QueueClientConfigContract, QueueBeanstalkdClientConfigContract
{
    /** @var class-string<ClientContract> */
    public string $defaultQueueClient = BeanstalkdClient::class;

    /**
     * @param non-empty-string $queueBeanstalkdClientHost           The host of the server under test
     * @param non-empty-string $queueBeanstalkdClientTube           The tube jobs are published to
     * @param int<0, max>      $queueBeanstalkdClientTimeToRelease  The seconds a reserved job is held
     * @param int<1, max>      $queueBeanstalkdClientReserveTimeout The seconds a reserve waits
     */
    public function __construct(
        public string $queueBeanstalkdClientHost = '127.0.0.1',
        public int $queueBeanstalkdClientPort = 11300,
        public string $queueBeanstalkdClientTube = 'valkyrja',
        public int $queueBeanstalkdClientTimeToRelease = BeanstalkdClient::DEFAULT_TIME_TO_RELEASE,
        public int $queueBeanstalkdClientReserveTimeout = 1,
    ) {
        parent::__construct(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
