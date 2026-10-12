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

use Valkyrja\Queue\Client\Data\Contract\QueueAmqpClientConfigContract;

class QueueAmqpClientConfig implements QueueAmqpClientConfigContract
{
    /**
     * @param non-empty-string $queueAmqpClientHost     The host to connect to
     * @param int              $queueAmqpClientPort     The port to connect to
     * @param non-empty-string $queueAmqpClientUser     The user to connect as
     * @param string           $queueAmqpClientPassword The password of the user
     * @param non-empty-string $queueAmqpClientVhost    The virtual host to connect to
     * @param non-empty-string $queueAmqpClientQueue    The queue jobs are published to
     * @param string           $queueAmqpClientExchange The exchange to publish through; empty for the default
     */
    public function __construct(
        public readonly string $queueAmqpClientHost = '127.0.0.1',
        public readonly int $queueAmqpClientPort = 5672,
        public readonly string $queueAmqpClientUser = 'guest',
        public readonly string $queueAmqpClientPassword = 'guest',
        public readonly string $queueAmqpClientVhost = '/',
        public readonly string $queueAmqpClientQueue = 'queues.default',
        public readonly string $queueAmqpClientExchange = '',
    ) {
    }
}
