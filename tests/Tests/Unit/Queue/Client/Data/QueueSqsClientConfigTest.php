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

        self::assertSame('us-east-1', $config->sqsRegion);
        self::assertNull($config->sqsEndpoint);
        self::assertNull($config->sqsAccessKeyId);
        self::assertNull($config->sqsAccessKeySecret);
        self::assertSame('https://sqs.us-east-1.amazonaws.com/000000000000/default', $config->sqsQueueUrl);
        self::assertSame(20, $config->sqsWaitTimeSeconds);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueSqsClientConfig(
            sqsRegion: 'eu-west-1',
            sqsEndpoint: 'http://sqs.test',
            sqsAccessKeyId: 'key',
            sqsAccessKeySecret: 'secret',
            sqsQueueUrl: 'http://sqs.test/000000000000/test',
            sqsWaitTimeSeconds: 5,
        );

        self::assertSame('eu-west-1', $config->sqsRegion);
        self::assertSame('http://sqs.test', $config->sqsEndpoint);
        self::assertSame('key', $config->sqsAccessKeyId);
        self::assertSame('secret', $config->sqsAccessKeySecret);
        self::assertSame('http://sqs.test/000000000000/test', $config->sqsQueueUrl);
        self::assertSame(5, $config->sqsWaitTimeSeconds);
    }
}
