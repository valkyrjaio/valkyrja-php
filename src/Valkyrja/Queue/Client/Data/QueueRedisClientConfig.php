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
     * @param non-empty-string $queueRedisClientHost       The host to connect to
     * @param int              $queueRedisClientPort       The port to connect to
     * @param string           $queueRedisClientPrefix     The prefix to prepend to every key
     * @param non-empty-string $queueRedisClientQueue      The list key jobs are pushed onto
     * @param non-empty-string $queueRedisClientWorkerName The name of this worker's slot
     */
    public function __construct(
        public readonly string $queueRedisClientHost = '127.0.0.1',
        public readonly int $queueRedisClientPort = 6379,
        public readonly string $queueRedisClientPrefix = '',
        public readonly string $queueRedisClientQueue = 'queues:default',
        public readonly string $queueRedisClientWorkerName = 'default',
    ) {
    }
}
