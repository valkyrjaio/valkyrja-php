<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\Database;

use JsonException;
use Override;
use Valkyrja\Application\Entry\Abstract\PullQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDatabaseClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DatabaseClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Support\Time\Microtime;

use function ctype_digit;
use function is_int;
use function is_string;
use function sleep;

class DatabaseQueue extends PullQueue
{
    /**
     * The age at which a claim is treated as abandoned, in milliseconds.
     *
     * A database has no native visibility timeout, so the reservation needs one
     * of its own. Without it a worker that dies between the claim and the
     * settle strands its row: no other worker can ever take it.
     *
     * Warning: a job that runs longer than this can be taken by a second
     * worker. Set the value above the longest a job may run.
     */
    public const int DEFAULT_RESERVATION_TIMEOUT_MS = 300_000;

    /** @var int<1, max> The age at which a claim is abandoned */
    protected static int $reservationTimeoutMs = self::DEFAULT_RESERVATION_TIMEOUT_MS;

    /** @var int<0, max> The seconds to yield when nothing was waiting; 0 to poll without pausing */
    protected static int $pollInterval = 1;

    protected static ManagerContract|null $manager = null;

    /** @var non-empty-string */
    protected static string $queue = 'default';

    /** @var non-empty-string */
    protected static string $table = DatabaseClient::DEFAULT_TABLE;

    /**
     * The id of the row currently reserved, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough, and
     * it is cleared on settlement so a second settle cannot act twice.
     */
    protected static int|null $current = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        $container = $app->getContainer();
        $config    = $container->getSingleton(QueueDatabaseClientConfigContract::class);

        static::$queue   = $config->databaseQueue;
        static::$table   = $config->databaseTable;
        static::$manager = static::getManager($container);
    }

    /**
     * @inheritDoc
     *
     * @throws QueueServerNotConnectedException
     */
    #[Override]
    public static function receive(): JobContract|null
    {
        $row = static::findEligible();

        if ($row === null) {
            static::wait();

            return null;
        }

        [$id, $envelope] = $row;

        if (! static::claim($id)) {
            // Another worker claimed the row between the read and the write.
            // Work is known to exist, so the loop asks again at once rather
            // than pausing exactly when the backlog is deepest.
            return null;
        }

        $job = static::decode($envelope, $id);

        if ($job === null) {
            return null;
        }

        static::$current = $id;

        return $job;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        // Anything still reserved was not completed, so hand it back rather
        // than leaving a row no worker will ever claim again
        static::releaseCurrent();

        static::$manager = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $id = static::$current;

        if ($id === null) {
            return;
        }

        static::$current = null;

        // The reserved row is spent whatever the outcome: a retry arrives as a
        // fresh row carrying the incremented attempt count
        static::delete($id);

        // A table owns no retry loop, so the framework publishes the job again
        if ($result === JobResult::RETRY) {
            $client->requeue($job);
        }
    }

    /**
     * Read the envelope, and take a row the factory cannot read off the table.
     *
     * An unreadable row never reaches the handler, so nothing settles it. The
     * claim lapses after the reservation timeout, the next worker reads the same
     * bytes, and nothing ends that. A database carries no dead-letter store of
     * its own, so the row is dropped, the same as an unreadable Redis envelope.
     */
    protected static function decode(string $envelope, int $id): JobContract|null
    {
        try {
            return new JobFactory()->fromJson($envelope);
        } catch (JsonException|QueueMessageInvalidEnvelopeException) {
            static::delete($id);

            return null;
        }
    }

    /**
     * Get the manager the loop reads the table with.
     */
    protected static function getManager(ContainerContract $container): ManagerContract
    {
        return $container->getSingleton(ManagerContract::class);
    }

    /**
     * Get the manager that connect() resolved.
     *
     * @throws QueueServerNotConnectedException
     */
    protected static function getConnection(): ManagerContract
    {
        return static::$manager
            ?? throw new QueueServerNotConnectedException('The database queue has no manager to read with.');
    }

    /**
     * Yield for the configured interval when nothing was waiting.
     *
     * A table does not block on a read, so this entry paces itself. Without the
     * yield an idle worker issues one select as fast as PHP can, which pegs a
     * core and streams queries at the database for the life of the process.
     */
    protected static function wait(): void
    {
        if (static::$pollInterval > 0) {
            static::pause(static::$pollInterval);
        }
    }

    /**
     * Yield the process for the given seconds.
     *
     * An irreducible wall-clock call, isolated behind an overridable seam so a
     * test can drive the surrounding branch without waiting it out.
     *
     * @param int<1, max> $seconds The seconds to pause
     *
     * @codeCoverageIgnore
     */
    protected static function pause(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * Find the next job whose hold has elapsed and that no worker holds.
     *
     * @return array{0: int, 1: string}|null
     */
    protected static function findEligible(): array|null
    {
        $now   = Microtime::getMilliseconds();
        $table = static::$table;

        $statement = static::getConnection()->prepare(
            "SELECT id, envelope FROM $table"
            . ' WHERE queue = :queue AND available_at_ms <= :now'
            . ' AND (reserved_at_ms IS NULL OR reserved_at_ms <= :stale)'
            . ' ORDER BY priority DESC, id ASC LIMIT 1'
        );

        $statement->bindValue(new Value('queue', static::$queue));
        $statement->bindValue(new Value('now', $now));
        $statement->bindValue(new Value('stale', $now - static::$reservationTimeoutMs));
        $statement->execute();

        // fetchAll, not fetch: the ORM treats an empty result as an error, and
        // an empty queue is the normal case here rather than a failure
        $row = $statement->fetchAll()[0] ?? [];

        // An idle queue is the normal case, and it is not an unreadable row
        if ($row === []) {
            return null;
        }

        $id       = $row['id'] ?? null;
        $envelope = $row['envelope'] ?? null;

        // Without a key there is nothing to name the row by, and an unqualified
        // delete would take whichever row is at the head instead. The row
        // therefore stays, and the queue stalls on it. The documented DDL makes
        // `id` the primary key, so this needs a table that allows it to be null.
        if (! is_int($id) && ! is_string($id)) {
            return null;
        }

        // A driver hands a BIGINT back as text on pgsql, and on mysql whenever
        // it emulates prepares, so a numeric string is the expected shape. Any
        // other string would cast to 0, and on mysql `WHERE id = 0` matches
        // every row with a non-numeric key, so the delete would take them too.
        if (! is_string($envelope) || (is_string($id) && ! ctype_digit($id))) {
            // A select takes nothing off the table, so a row this method cannot
            // read stays at the queue head and is handed back on every poll.
            // `decode()` settled the policy for a row the adapter cannot read:
            // drop it, because nothing else ends the loop. The key the select
            // returned is exact here, so the delete names it rather than
            // repeating the predicate and taking whichever row is at the head.
            static::delete($id);

            return null;
        }

        return [(int) $id, $envelope];
    }

    /**
     * Take ownership of a row, if no other worker took it first.
     *
     * The write is conditional, so two workers reading the same row cannot both
     * win: the second one updates nothing.
     */
    protected static function claim(int $id): bool
    {
        $now   = Microtime::getMilliseconds();
        $table = static::$table;

        $statement = static::getConnection()->prepare(
            "UPDATE $table SET reserved_at_ms = :now"
            . ' WHERE id = :id AND (reserved_at_ms IS NULL OR reserved_at_ms <= :stale)'
        );

        $statement->bindValue(new Value('now', $now));
        $statement->bindValue(new Value('stale', $now - static::$reservationTimeoutMs));
        $statement->bindValue(new Value('id', $id));
        $statement->execute();

        return $statement->getRowCount() > 0;
    }

    /**
     * Hand any reserved row back to the queue.
     */
    protected static function releaseCurrent(): void
    {
        $id = static::$current;

        if ($id !== null) {
            static::$current = null;
            $table           = static::$table;

            $statement = static::getConnection()->prepare(
                "UPDATE $table SET reserved_at_ms = NULL WHERE id = :id"
            );

            $statement->bindValue(new Value('id', $id));
            $statement->execute();
        }
    }

    /**
     * Take a row off the table for good.
     */
    protected static function delete(int|string $id): void
    {
        $table = static::$table;

        $statement = static::getConnection()->prepare("DELETE FROM $table WHERE id = :id");

        $statement->bindValue(new Value('id', $id));
        $statement->execute();
    }
}
