<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Data;

use Valkyrja\Queue\Client\Data\Contract\QueueSqsClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueSqsClientConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueueSqsClientConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(QueueSqsClientConfigContract::class, new QueueSqsClientConfig());
    }

    public function testDefaults(): void
    {
        $config = new QueueSqsClientConfig();

        self::assertSame('us-east-1', $config->queueSqsClientRegion);
        self::assertNull($config->queueSqsClientEndpoint);
        self::assertNull($config->queueSqsClientAccessKeyId);
        self::assertNull($config->queueSqsClientAccessKeySecret);
        self::assertSame('https://sqs.us-east-1.amazonaws.com/000000000000/default', $config->queueSqsClientQueueUrl);
        self::assertSame(20, $config->queueSqsClientWaitTimeSeconds);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueSqsClientConfig(
            queueSqsClientRegion: 'eu-west-1',
            queueSqsClientEndpoint: 'http://sqs.test',
            queueSqsClientAccessKeyId: 'key',
            queueSqsClientAccessKeySecret: 'secret',
            queueSqsClientQueueUrl: 'http://sqs.test/000000000000/test',
            queueSqsClientWaitTimeSeconds: 5,
        );

        self::assertSame('eu-west-1', $config->queueSqsClientRegion);
        self::assertSame('http://sqs.test', $config->queueSqsClientEndpoint);
        self::assertSame('key', $config->queueSqsClientAccessKeyId);
        self::assertSame('secret', $config->queueSqsClientAccessKeySecret);
        self::assertSame('http://sqs.test/000000000000/test', $config->queueSqsClientQueueUrl);
        self::assertSame(5, $config->queueSqsClientWaitTimeSeconds);
    }
}
