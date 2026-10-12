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

use Valkyrja\Queue\Client\Data\Contract\QueueSqsClientConfigContract;

class QueueSqsClientConfig implements QueueSqsClientConfigContract
{
    /**
     * @param non-empty-string      $queueSqsClientRegion          The AWS region
     * @param non-empty-string|null $queueSqsClientEndpoint        The endpoint to send to; null for the AWS endpoint of the region
     * @param non-empty-string|null $queueSqsClientAccessKeyId     The access key id; null for the AWS credential chain
     * @param non-empty-string|null $queueSqsClientAccessKeySecret The access key secret; null for the AWS credential chain
     * @param non-empty-string      $queueSqsClientQueueUrl        The URL of the queue that jobs are sent to
     * @param int<1, 20>            $queueSqsClientWaitTimeSeconds The long-poll wait; 20 is the longest and cheapest
     */
    public function __construct(
        public readonly string $queueSqsClientRegion = 'us-east-1',
        public readonly string|null $queueSqsClientEndpoint = null,
        public readonly string|null $queueSqsClientAccessKeyId = null,
        public readonly string|null $queueSqsClientAccessKeySecret = null,
        public readonly string $queueSqsClientQueueUrl = 'https://sqs.us-east-1.amazonaws.com/000000000000/default',
        public readonly int $queueSqsClientWaitTimeSeconds = 20,
    ) {
    }
}
