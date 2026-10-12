<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Data;

use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSqsClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\SqsClient;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;

/**
 * A worker application that produces and consumes through a real SQS endpoint.
 */
final class SqsWorkerConfigFixture extends QueueConfig implements QueueClientConfigContract, QueueSqsClientConfigContract
{
    /** @var class-string<ClientContract> */
    public string $defaultQueueClient = SqsClient::class;

    /**
     * @param non-empty-string      $queueSqsClientRegion          The region of the endpoint under test
     * @param non-empty-string|null $queueSqsClientEndpoint        The endpoint, when it is not the real one
     * @param non-empty-string|null $queueSqsClientAccessKeyId     The access key
     * @param non-empty-string|null $queueSqsClientAccessKeySecret The access secret
     * @param non-empty-string      $queueSqsClientQueueUrl        The queue jobs are published to
     * @param int<1, 20>            $queueSqsClientWaitTimeSeconds The long-poll wait; 1 keeps the suite quick
     */
    public function __construct(
        public string $queueSqsClientQueueUrl = 'http://localhost:9325/000000000000/valkyrja-test',
        public string $queueSqsClientRegion = 'us-east-1',
        public string|null $queueSqsClientEndpoint = null,
        public string|null $queueSqsClientAccessKeyId = 'key',
        public string|null $queueSqsClientAccessKeySecret = 'secret',
        public int $queueSqsClientWaitTimeSeconds = 1,
    ) {
        parent::__construct(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
