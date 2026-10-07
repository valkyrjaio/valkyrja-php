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

use AsyncAws\Sqs\Enum\MessageSystemAttributeName;
use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\ValueObject\Message;
use JsonException;
use Override;
use Valkyrja\Application\Entry\Abstract\PullQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSqsClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\SqsClient as ValkyrjaSqsClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Support\Time\Microtime;

use function ceil;
use function max;
use function min;
use function preg_match;

class SqsQueue extends PullQueue
{
    /** The longest hold SQS accepts on a visibility timeout, in seconds. */
    public const int MAX_VISIBILITY_TIMEOUT = 43_200;

    /**
     * The long-poll wait; 0 polls without blocking.
     *
     * Twenty is the longest SQS accepts and the cheapest: a one-second wait
     * bills roughly 86,400 `ReceiveMessage` calls a day on an idle queue.
     *
     * @var int<1, 20>
     */
    protected static int $waitTimeSeconds = 20;

    /** When the delivery in flight was received, as a unix timestamp */
    protected static float $receivedAt = 0.0;

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

        static::$queueUrl        = $config->sqsQueueUrl;
        static::$waitTimeSeconds = $config->sqsWaitTimeSeconds;
        static::$sqs             = static::getSqs($config);
    }

    /**
     * @inheritDoc
     *
     * @throws QueueServerNotConnectedException
     */
    #[Override]
    public static function receive(): JobContract|null
    {
        $result = static::getConnection()->receiveMessage([
            'QueueUrl'                    => static::getQueueUrl(),
            'MaxNumberOfMessages'         => 1,
            'WaitTimeSeconds'             => static::$waitTimeSeconds,
            // No VisibilityTimeout: the queue's own setting is the operator's,
            // and settle() names the hold a retry needs explicitly

            // SQS owns the attempt count, and it only reports it when asked
            'MessageSystemAttributeNames' => [MessageSystemAttributeName::APPROXIMATE_RECEIVE_COUNT],
        ]);

        $message = $result->getMessages()[0] ?? null;

        if ($message === null) {
            return null;
        }

        $handle = $message->getReceiptHandle();

        // Settling needs the handle, so a delivery without one can be neither
        // deleted nor made visible again. Running it would leave SQS to
        // redeliver the same message on every visibility timeout, forever
        if ($handle === null) {
            return null;
        }

        $body = $message->getBody();

        // A delivery with no body cannot be run, and leaving it in flight would
        // poison every later poll, so it is retired rather than handed back
        if ($body === null) {
            static::retire($handle);

            return null;
        }

        $job = static::decode($body, $handle);

        if ($job === null) {
            return null;
        }

        static::$current    = $handle;
        static::$receivedAt = Microtime::get();

        return static::withNormalizedAttempts($job, $message);
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
            // SQS redelivers once the visibility timeout lapses, and it counts
            // the receive. That timeout is the one hold it offers, so a retrying
            // job waits for its ramp rather than burning every attempt at once.
            static::changeVisibility($handle, static::getVisibilityTimeout($job));

            return;
        }

        // A dead letter is terminal here as well. SQS moves a message to the
        // dead-letter queue through the redrive policy, on the receive count,
        // so the framework deleting it is what stops the chain.
        static::delete($handle);
    }

    /**
     * Read the envelope, and retire a body the factory cannot read.
     *
     * An unreadable body never reaches the handler, so nothing settles it. SQS
     * would hand the same message back on every visibility timeout, and a queue
     * without a redrive policy has nothing to end that.
     */
    protected static function decode(string $body, string $handle): JobContract|null
    {
        try {
            return new JobFactory()->fromJson($body);
        } catch (JsonException|QueueMessageInvalidEnvelopeException) {
            static::retire($handle);

            return null;
        }
    }

    /**
     * Take a delivery off the queue without running it.
     */
    protected static function retire(string $handle): void
    {
        static::delete($handle);
    }

    /**
     * Take a delivery off the queue for good.
     *
     * The request is lazy, so an answer nobody resolves is an error nobody
     * sees, and a delete that failed would let the delivery run a second time.
     */
    protected static function delete(string $handle): void
    {
        static::getConnection()->deleteMessage([
            'QueueUrl'      => static::getQueueUrl(),
            'ReceiptHandle' => $handle,
        ])->resolve();
    }

    /**
     * Read the receive count back off the queue and onto the job.
     *
     * A processor-owned adapter never rewrites the envelope, so the `attempts`
     * the producer published never advances on its own. SQS owns the count, and
     * the adapter normalizes it, which is what lets `max_attempts` stop a
     * failing chain. A count that is absent or not a positive integer leaves
     * the envelope's own value in place.
     */
    protected static function withNormalizedAttempts(JobContract $job, Message $message): JobContract
    {
        $count = $message->getAttributes()[MessageSystemAttributeName::APPROXIMATE_RECEIVE_COUNT] ?? null;

        if ($count === null || preg_match('/^[1-9][0-9]*$/', $count) !== 1) {
            return $job;
        }

        // The regex admits only a positive integer; max() is what narrows it
        return $job->withAttempts(max(1, (int) $count));
    }

    /**
     * Build the client the loop polls.
     *
     * @codeCoverageIgnore A real SQS endpoint is unavailable in a test.
     */
    protected static function getSqs(QueueSqsClientConfigContract $config): SqsClient
    {
        return ValkyrjaSqsClient::createSqs($config);
    }

    /**
     * Get the queue url that connect() read from the config.
     *
     * `connect()` writes the url and the client together and every caller reads
     * the client first, so the client guard is the one that answers an entry
     * that never connected. This guard stays because the two are separate
     * fields, and it keeps the return type free of a null a caller cannot get.
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
     * The visibility timeout that holds the next attempt, in whole seconds.
     *
     * SQS takes whole seconds, and the envelope holds milliseconds, so a
     * sub-second hold rounds up rather than down to no hold at all.
     *
     * @return int<0, 43200>
     */
    protected static function getVisibilityTimeout(JobContract $job): int
    {
        $milliseconds = $job->getRetryDelayForAttemptMs();

        if ($milliseconds === 0) {
            return 0;
        }

        return max(1, min((int) ceil($milliseconds / 1000), static::getVisibilityCeiling()));
    }

    /**
     * The longest hold this delivery can still be given.
     *
     * SQS measures the ceiling from the receive and not from the call, so
     * asking for the whole twelve hours once any of it has passed is rejected.
     *
     * @return int<1, 43200>
     */
    protected static function getVisibilityCeiling(): int
    {
        $elapsed = (int) ceil(Microtime::get() - static::$receivedAt);

        /** @var int<1, 43200> the subtraction cannot exceed the constant */
        return max(1, self::MAX_VISIBILITY_TIMEOUT - max(0, $elapsed));
    }

    /**
     * Set how long a delivery stays hidden from other consumers.
     *
     * @param int<0, max> $timeout The seconds to stay hidden; 0 makes it visible at once
     */
    protected static function changeVisibility(string $handle, int $timeout): void
    {
        // The request is lazy, so an answer nobody resolves is an error nobody
        // sees, and a hold that never took leaves the retry on the queue's own
        // window instead of the one the job asked for
        static::getConnection()->changeMessageVisibility([
            'QueueUrl'          => static::getQueueUrl(),
            'ReceiptHandle'     => $handle,
            'VisibilityTimeout' => $timeout,
        ])->resolve();
    }
}
