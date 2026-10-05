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
     * @param non-empty-string      $sqsRegion          The AWS region
     * @param non-empty-string|null $sqsEndpoint        The endpoint to send to; null for the AWS endpoint of the region
     * @param non-empty-string|null $sqsAccessKeyId     The access key id; null for the AWS credential chain
     * @param non-empty-string|null $sqsAccessKeySecret The access key secret; null for the AWS credential chain
     * @param non-empty-string      $sqsQueueUrl        The URL of the queue that jobs are sent to
     * @param int<0, 20>            $sqsWaitTimeSeconds The long-poll wait; 20 is the longest and cheapest
     */
    public function __construct(
        public readonly string $sqsRegion = 'us-east-1',
        public readonly string|null $sqsEndpoint = null,
        public readonly string|null $sqsAccessKeyId = null,
        public readonly string|null $sqsAccessKeySecret = null,
        public readonly string $sqsQueueUrl = 'https://sqs.us-east-1.amazonaws.com/000000000000/default',
        public readonly int $sqsWaitTimeSeconds = 20,
    ) {
    }
}
