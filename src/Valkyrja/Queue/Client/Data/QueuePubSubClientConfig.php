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

use Valkyrja\Queue\Client\Data\Contract\QueuePubSubClientConfigContract;

class QueuePubSubClientConfig implements QueuePubSubClientConfigContract
{
    /**
     * @param non-empty-string $queuePubSubClientProjectId The Google Cloud project
     * @param non-empty-string $queuePubSubClientTopic     The topic that jobs are published to
     */
    public function __construct(
        public readonly string $queuePubSubClientProjectId = 'valkyrja',
        public readonly string $queuePubSubClientTopic = 'default',
    ) {
    }
}
