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

    /**
     * Return every envelope this worker's slot still holds to the ready list.
     *
     * The move is onto the head, so a reclaimed job is redelivered before the
     * backlog behind it. The slot's list is read by this worker alone, so a
     * reclaim cannot take an envelope a live worker is running.
     */
    protected const string RECLAIM_SCRIPT = <<<'LUA'
        local moved = 0

        for _ = 1, tonumber(ARGV[1]) do
            if not redis.call('LMOVE', KEYS[1], KEYS[2], 'LEFT', 'LEFT') then
                break
            end

            moved = moved + 1
        end

        return moved
        LUA;

    /** @var int<1, max> The blocking move timeout, in seconds */
    protected static int $timeout = 1;

    /** @var int<1, max> The number of due delayed jobs one poll promotes */
    protected static int $promoteLimit = 100;

    /** @var int<1, max> The number of held envelopes one reclaim returns */
    protected static int $reclaimLimit = 1000;

    /** @var non-empty-string The name of this worker's slot */
    protected static string $workerName = 'default';

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

        static::$queue      = $config->redisQueue;
        static::$workerName = $config->redisWorkerName;
        static::$redis      = static::getRedis($config);
        static::$current    = null;

        $redis = static::getConnection();

        $redis->connect();

        // A worker that died mid-job left its envelope on this slot's list, so
        // the slot takes its own work back on the way in. Only a slot that
        // never returns needs a hand, which `Queue/README.md` states.
        static::reclaim($redis);
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
        $redis = static::getConnection();

        // The loop is done with the slot, so anything still held goes back on
        // the ready list rather than waiting for the next start of this slot
        static::reclaim($redis);

        $redis->disconnect();

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
        // One key per worker slot, so a reclaim returns only this worker's own
        // held envelope and never one a live worker is running
        return static::$queue . self::IN_FLIGHT_SUFFIX . ':' . static::$workerName;
    }

    /**
     * Return every envelope this slot holds to the ready list.
     */
    protected static function reclaim(ClientInterface $redis): void
    {
        $redis->eval(
            self::RECLAIM_SCRIPT,
            2,
            static::inFlight(),
            static::$queue,
            (string) static::$reclaimLimit,
        );
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
