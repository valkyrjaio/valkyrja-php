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

use function is_string;

class RedisQueue extends PullQueue
{
    /** The key suffix of the list a received job sits on until it settles. */
    public const string IN_FLIGHT_SUFFIX = ':inflight';

    /** The key suffix of the list an envelope no factory can read is parked on. */
    public const string UNREADABLE_SUFFIX = ':unreadable';

    /**
     * Promote every due delayed job, atomically and in a bounded batch.
     *
     * The removal gates the enqueue, so two workers polling at once cannot
     * promote the same job twice, and running both inside one script means a
     * worker that dies between them cannot leave the job on neither key. The
     * limit bounds the walk, so a backlog cannot stall the poll loop.
     */
    protected const string PROMOTE_SCRIPT = <<<'LUA'
        local due = redis.call('ZRANGEBYSCORE', KEYS[1], '-inf', ARGV[1], 'LIMIT', 0, ARGV[2])

        for index = 1, #due do
            if redis.call('ZREM', KEYS[1], due[index]) == 1 then
                redis.call('RPUSH', KEYS[2], due[index])
            end
        end

        return #due
        LUA;

    /** @var int<1, max> The blocking move timeout, in seconds */
    protected static int $timeout = 1;

    /** @var int<1, max> The number of due delayed jobs one poll promotes */
    protected static int $promoteLimit = 100;

    protected static ClientInterface|null $redis = null;

    /** @var non-empty-string */
    protected static string $queue = 'queues:default';

    /** The envelope this worker holds on the in-flight list, if any. */
    protected static string|null $current = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        $config = $app->getContainer()->getSingleton(QueueRedisClientConfigContract::class);

        static::$queue   = $config->redisQueue;
        static::$redis   = static::getRedis($config);
        static::$current = null;

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

        // A blocking move, so the envelope reaches the in-flight list in the
        // same step that takes it off the ready list. A plain pop would leave a
        // received job in this process's memory alone, where a crash, an OOM,
        // or a deploy that does not wait the job out loses it with no record.
        /** @var mixed $envelope */
        $envelope = $redis->blmove(static::$queue, static::inFlight(), 'LEFT', 'RIGHT', static::$timeout);

        // A move that timed out returns nothing
        if (! is_string($envelope)) {
            return null;
        }

        $job = static::decode($envelope);

        if ($job === null) {
            static::park($redis, $envelope);

            return null;
        }

        static::$current = $envelope;

        return $job;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        static::getConnection()->disconnect();

        static::$redis   = null;
        static::$current = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $envelope = static::$current;

        static::$current = null;

        // Redis owns no retry loop, so the framework publishes again
        if ($result === JobResult::RETRY) {
            $client->requeue($job);
        }

        if ($envelope === null) {
            return;
        }

        // The in-flight copy goes last, so a worker that dies mid-settlement
        // leaves a duplicate delivery rather than no delivery at all
        static::getConnection()->lrem(static::inFlight(), 1, $envelope);
    }

    /**
     * The key of the list a received job sits on until it settles.
     *
     * @return non-empty-string
     */
    protected static function inFlight(): string
    {
        return static::$queue . self::IN_FLIGHT_SUFFIX;
    }

    /**
     * Read an envelope off the list, or nothing when it cannot be read.
     *
     * The shape guard above tolerates every other way a move goes wrong, so a
     * body that is a string but not a readable envelope is tolerated the same
     * way, and the caller parks it rather than throwing and taking the worker
     * down with it.
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
     * Park an envelope no factory can read, so it leaves a record.
     *
     * A discard would retire the message while the worker kept reporting
     * healthy. The push comes before the removal, so a worker that dies between
     * them leaves the envelope on both keys rather than on neither.
     */
    protected static function park(ClientInterface $redis, string $envelope): void
    {
        $redis->rpush(static::$queue . self::UNREADABLE_SUFFIX, [$envelope]);
        $redis->lrem(static::inFlight(), 1, $envelope);
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
        $redis->eval(
            self::PROMOTE_SCRIPT,
            2,
            static::$queue . RedisClient::DELAYED_SUFFIX,
            static::$queue,
            (string) Microtime::getMilliseconds(),
            (string) static::$promoteLimit,
        );
    }
}
