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

use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;

class QueueRedisClientConfig implements QueueRedisClientConfigContract
{
    /**
     * @param non-empty-string $redisHost       The host to connect to
     * @param int              $redisPort       The port to connect to
     * @param non-empty-string $redisQueue      The list key jobs are pushed onto
     * @param non-empty-string $redisWorkerName The name of this worker's slot
     */
    public function __construct(
        public readonly string $redisHost = '127.0.0.1',
        public readonly int $redisPort = 6379,
        public readonly string $redisQueue = 'queues:default',
        public readonly string $redisWorkerName = 'default',
    ) {
    }
}
