<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\Redis;

use JsonException;
use Override;
use Predis\Client;
use Predis\ClientInterface;
use Valkyrja\Application\Entry\Abstract\PullQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Support\Time\Microtime;

use function is_array;
use function is_string;

class RedisQueue extends PullQueue
{
    /** @var int<1, max> The blocking pop timeout, in seconds */
    protected static int $timeout = 1;

    protected static ClientInterface|null $redis = null;

    /** @var non-empty-string */
    protected static string $queue = 'queues:default';

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        $config = $app->getContainer()->getSingleton(QueueRedisClientConfigContract::class);

        static::$queue = $config->redisQueue;
        static::$redis = static::getRedis($config);

        static::getConnection()->connect();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function receive(): JobContract|null
    {
        $redis = static::getConnection();

        static::promoteDelayed($redis);

        $popped = $redis->blpop([static::$queue], static::$timeout);

        // A blocking pop returns [key, value]; a timeout returns nothing
        if (! is_array($popped) || ! isset($popped[1]) || ! is_string($popped[1])) {
            return null;
        }

        return static::decode($popped[1]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        static::getConnection()->disconnect();

        static::$redis = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        // A popped job is already off the list, so every terminal outcome needs
        // nothing. Redis owns no retry loop, so the framework publishes again.
        if ($result === JobResult::RETRY) {
            $client->requeue($job);
        }
    }

    /**
     * Read an envelope off the list, or nothing when it cannot be read.
     *
     * The shape guards above tolerate every other way a pop goes wrong, so a
     * body that is a string but not a readable envelope is tolerated the same
     * way. The pop already removed it, so throwing would lose the job and take
     * the worker down with it.
     */
    protected static function decode(string $envelope): JobContract|null
    {
        try {
            return new JobFactory()->fromJson($envelope);
        } catch (JsonException|QueueMessageInvalidEnvelopeException) {
            return null;
        }
    }

    /**
     * Build the connection the loop polls.
     *
     * @codeCoverageIgnore A real redis connection is unavailable in a test.
     */
    protected static function getRedis(QueueRedisClientConfigContract $config): ClientInterface
    {
        return new Client(
            parameters: [
                'host' => $config->redisHost,
                'port' => $config->redisPort,
            ]
        );
    }

    /**
     * Get the connection that connect() established.
     *
     * @throws QueueServerNotConnectedException
     */
    protected static function getConnection(): ClientInterface
    {
        return static::$redis
            ?? throw new QueueServerNotConnectedException('The redis queue has no connection to poll.');
    }

    /**
     * Move every delayed job whose hold has elapsed onto the ready list.
     */
    protected static function promoteDelayed(ClientInterface $redis): void
    {
        $delayedQueue = static::$queue . RedisClient::DELAYED_SUFFIX;

        /** @var mixed $due */
        $due = $redis->zrangebyscore($delayedQueue, '-inf', (string) Microtime::now());

        if (! is_array($due)) {
            return;
        }

        /** @var mixed $envelope */
        foreach ($due as $envelope) {
            if (! is_string($envelope)) {
                continue;
            }

            // Only the worker that wins the removal may enqueue it, so a job
            // cannot be promoted twice by two workers polling at once
            if ($redis->zrem($delayedQueue, $envelope) > 0) {
                $redis->rpush(static::$queue, [$envelope]);
            }
        }
    }
}
