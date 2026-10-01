<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\Sqs;

use AsyncAws\Sqs\SqsClient;
use JsonException;
use Override;
use Valkyrja\Application\Entry\Abstract\PullQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSqsClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;

use function array_filter;

class SqsQueue extends PullQueue
{
    /** @var int<0, 20> The long-poll wait; 0 polls without blocking */
    protected static int $waitTimeSeconds = 1;

    /** @var int<0, max> The seconds a delivery stays hidden from other consumers */
    protected static int $visibilityTimeout = 30;

    protected static SqsClient|null $sqs = null;

    /** @var non-empty-string|null */
    protected static string|null $queueUrl = null;

    /**
     * The receipt handle of the delivery currently in flight, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough, and
     * it is cleared on settlement so a second settle cannot double-delete.
     */
    protected static string|null $current = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        $config = $app->getContainer()->getSingleton(QueueSqsClientConfigContract::class);

        static::$queueUrl = $config->sqsQueueUrl;
        static::$sqs      = static::getSqs($config);
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public static function receive(): JobContract|null
    {
        $result = static::getConnection()->receiveMessage([
            'QueueUrl'            => static::getQueueUrl(),
            'MaxNumberOfMessages' => 1,
            'WaitTimeSeconds'     => static::$waitTimeSeconds,
            'VisibilityTimeout'   => static::$visibilityTimeout,
        ]);

        $message = $result->getMessages()[0] ?? null;

        if ($message === null) {
            return null;
        }

        $body = $message->getBody();

        if ($body === null) {
            return null;
        }

        $handle = $message->getReceiptHandle();

        // Settling needs the handle, so a delivery without one can be neither
        // deleted nor made visible again. Running it would leave SQS to
        // redeliver the same message on every visibility timeout, forever
        if ($handle === null) {
            return null;
        }

        static::$current = $handle;

        return new JobFactory()->fromJson($body);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        // Anything still in flight was not completed, so release it rather than
        // letting it wait out the whole visibility timeout
        static::releaseCurrent();

        static::$sqs = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $handle = static::$current;

        if ($handle === null) {
            return;
        }

        static::$current = null;

        if ($result === JobResult::RETRY) {
            // Make it visible again: SQS redelivers and counts the receives
            static::changeVisibility($handle, 0);

            return;
        }

        // A dead letter is terminal here as well. SQS moves a message to the
        // dead-letter queue through the redrive policy, on the receive count,
        // so the framework deleting it is what stops the chain.
        static::getConnection()->deleteMessage([
            'QueueUrl'      => static::getQueueUrl(),
            'ReceiptHandle' => $handle,
        ]);
    }

    /**
     * Build the client the loop polls.
     *
     * @codeCoverageIgnore A real SQS endpoint is unavailable in a test.
     */
    protected static function getSqs(QueueSqsClientConfigContract $config): SqsClient
    {
        return new SqsClient(
            array_filter(
                [
                    'region'          => $config->sqsRegion,
                    'endpoint'        => $config->sqsEndpoint,
                    'accessKeyId'     => $config->sqsAccessKeyId,
                    'accessKeySecret' => $config->sqsAccessKeySecret,
                ],
                // A null option falls back to the default of the SDK
                static fn (string|null $value): bool => $value !== null
            )
        );
    }

    /**
     * Get the queue url that connect() read from the config.
     *
     * @throws QueueServerNotConnectedException
     *
     * @return non-empty-string
     */
    protected static function getQueueUrl(): string
    {
        return static::$queueUrl
            ?? throw new QueueServerNotConnectedException('The SQS queue has no url to poll.');
    }

    /**
     * Get the client that connect() built.
     *
     * @throws QueueServerNotConnectedException
     */
    protected static function getConnection(): SqsClient
    {
        return static::$sqs
            ?? throw new QueueServerNotConnectedException('The SQS queue has no client to poll with.');
    }

    /**
     * Hand any in-flight delivery back to the queue.
     */
    protected static function releaseCurrent(): void
    {
        $handle = static::$current;

        if ($handle !== null) {
            static::$current = null;

            static::changeVisibility($handle, 0);
        }
    }

    /**
     * Set how long a delivery stays hidden from other consumers.
     *
     * @param int<0, max> $timeout The seconds to stay hidden; 0 makes it visible at once
     */
    protected static function changeVisibility(string $handle, int $timeout): void
    {
        static::getConnection()->changeMessageVisibility([
            'QueueUrl'          => static::getQueueUrl(),
            'ReceiptHandle'     => $handle,
            'VisibilityTimeout' => $timeout,
        ]);
    }
}
