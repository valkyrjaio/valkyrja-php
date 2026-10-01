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
     * @param non-empty-string $beanstalkdHost          The host of the server under test
     * @param non-empty-string $beanstalkdTube          The tube jobs are published to
     * @param int<0, max>      $beanstalkdTimeToRelease The seconds a reserved job is held
     */
    public function __construct(
        public string $beanstalkdHost = '127.0.0.1',
        public int $beanstalkdPort = 11300,
        public string $beanstalkdTube = 'valkyrja',
        public int $beanstalkdTimeToRelease = BeanstalkdClient::DEFAULT_TIME_TO_RELEASE,
    ) {
        parent::__construct(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
