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

use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\RedisClient;

class QueueClientConfig implements QueueClientConfigContract
{
    /**
     * @param class-string<ClientContract> $defaultQueueClient The client to use by default
     */
    public function __construct(
        public readonly string $defaultQueueClient = RedisClient::class,
    ) {
    }
}
