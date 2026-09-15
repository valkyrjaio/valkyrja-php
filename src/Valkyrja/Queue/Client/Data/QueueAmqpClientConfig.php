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
     * @param non-empty-string $amqpHost     The host to connect to
     * @param int              $amqpPort     The port to connect to
     * @param non-empty-string $amqpUser     The user to connect as
     * @param string           $amqpPassword The password of the user
     * @param non-empty-string $amqpVhost    The virtual host to connect to
     * @param non-empty-string $amqpQueue    The queue jobs are published to
     * @param string           $amqpExchange The exchange to publish through; empty for the default
     */
    public function __construct(
        public readonly string $amqpHost = '127.0.0.1',
        public readonly int $amqpPort = 5672,
        public readonly string $amqpUser = 'guest',
        public readonly string $amqpPassword = 'guest',
        public readonly string $amqpVhost = '/',
        public readonly string $amqpQueue = 'queues.default',
        public readonly string $amqpExchange = '',
    ) {
    }
}
