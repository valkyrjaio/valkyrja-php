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
     * @param non-empty-string $beanstalkdHost          The host to connect to
     * @param int              $beanstalkdPort          The port to connect to
     * @param non-empty-string $beanstalkdTube          The tube that jobs are put on
     * @param int<0, max>      $beanstalkdTimeToRelease The seconds a worker holds a job before beanstalkd releases it
     */
    public function __construct(
        public readonly string $beanstalkdHost = '127.0.0.1',
        public readonly int $beanstalkdPort = 11300,
        public readonly string $beanstalkdTube = 'default',
        public readonly int $beanstalkdTimeToRelease = BeanstalkdClient::DEFAULT_TIME_TO_RELEASE,
    ) {
    }
}
