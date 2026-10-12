<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Data;

use Valkyrja\Queue\Client\Data\Contract\QueueBeanstalkdClientConfigContract;
use Valkyrja\Queue\Client\Manager\BeanstalkdClient;

class QueueBeanstalkdClientConfig implements QueueBeanstalkdClientConfigContract
{
    /**
     * @param non-empty-string $queueBeanstalkdClientHost           The host to connect to
     * @param int              $queueBeanstalkdClientPort           The port to connect to
     * @param non-empty-string $queueBeanstalkdClientTube           The tube that jobs are put on
     * @param int<0, max>      $queueBeanstalkdClientTimeToRelease  The seconds a producer gives a worker before beanstalkd releases the job
     * @param int<1, max>      $queueBeanstalkdClientReserveTimeout The seconds a reserve waits for a job
     */
    public function __construct(
        public readonly string $queueBeanstalkdClientHost = '127.0.0.1',
        public readonly int $queueBeanstalkdClientPort = 11300,
        public readonly string $queueBeanstalkdClientTube = 'default',
        public readonly int $queueBeanstalkdClientTimeToRelease = BeanstalkdClient::DEFAULT_TIME_TO_RELEASE,
        public readonly int $queueBeanstalkdClientReserveTimeout = 1,
    ) {
    }
}
