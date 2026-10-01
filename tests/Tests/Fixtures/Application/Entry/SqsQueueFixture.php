<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Entry;

use AsyncAws\Sqs\SqsClient;
use Override;
use Valkyrja\Application\Entry\Sqs\SqsQueue;
use Valkyrja\Queue\Client\Data\Contract\QueueSqsClientConfigContract;

/**
 * An SQS queue entry that polls an injected client instead of a real one.
 */
final class SqsQueueFixture extends SqsQueue
{
    private static SqsClient|null $injected = null;

    /**
     * Point the entry at a client, as though connect() had built it.
     *
     * @param non-empty-string $queueUrl          The queue jobs are consumed from
     * @param int<0, 20>       $waitTimeSeconds   The long-poll wait
     * @param int<0, max>      $visibilityTimeout The seconds a delivery stays hidden
     */
    public static function inject(
        SqsClient $sqs,
        string $queueUrl,
        int $waitTimeSeconds = 2,
        int $visibilityTimeout = 45,
    ): void {
        self::$injected            = $sqs;
        self::$sqs                 = $sqs;
        self::$queueUrl            = $queueUrl;
        self::$waitTimeSeconds     = $waitTimeSeconds;
        self::$visibilityTimeout   = $visibilityTimeout;
    }

    /**
     * Drop the client and the overrides, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$injected            = null;
        self::$sqs                 = null;
        self::$current             = null;
        self::$queueUrl            = null;
        self::$waitTimeSeconds     = 1;
        self::$visibilityTimeout   = 30;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected static function getSqs(QueueSqsClientConfigContract $config): SqsClient
    {
        return self::$injected ?? parent::getSqs($config);
    }
}
