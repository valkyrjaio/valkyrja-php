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
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerInvalidRowKeyException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Support\Time\Microtime;

use function get_debug_type;
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

    /**
     * The reservation stamp a parked row carries.
     *
     * The last whole second of the year 9999, so no staleness window a worker
     * computes reaches back to it and a parked row is never claimed again.
     */
    public const int PARKED_AT_MS = 253_402_300_799_000;

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
    /** @var array{id: int|string, at: int}|null The row this worker holds, and the stamp it claimed with */
    protected static array|null $claim = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        $container = $app->getContainer();
        $config    = $container->getSingleton(QueueDatabaseClientConfigContract::class);

        static::$claim                = null;
        static::$queue                = $config->queueDatabaseClientQueue;
        static::$table                = $config->queueDatabaseClientTable;
        static::$reservationTimeoutMs = $config->queueDatabaseClientReservationTimeoutMs;
        static::$manager              = static::getManager($container);
    }

    /**
     * @inheritDoc
     *
     * @throws QueueServerInvalidRowKeyException
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

        $claimedAt = static::claim($id);

        if ($claimedAt === null) {
            // Another worker claimed the row between the read and the write.
            // Work is known to exist, so the loop asks again at once rather
            // than pausing exactly when the backlog is deepest.
            return null;
        }

        $job = static::decode($envelope, $id);

        if ($job === null) {
            return null;
        }

        static::$claim = ['id' => $id, 'at' => $claimedAt];

        return $job;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        try {
            // Anything still reserved was not completed, so hand it back
            // rather than leaving a row no worker will ever claim again
            static::releaseCurrent();
        } finally {
            // A release that throws must not leave this entry holding a
            // manager a later run would claim on
            static::$claim   = null;
            static::$manager = null;
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $claim = static::$claim;

        if ($claim === null) {
            return;
        }

        static::$claim = null;

        // A table owns no retry loop, so the framework publishes the job
        // again. The new row goes first: an insert that fails after the delete
        // committed would lose the job, where this order leaves a duplicate
        // delivery at worst.
        if ($result === JobResult::RETRY) {
            $client->requeue($job);
        }

        // The reserved row is spent whatever the outcome
        static::delete($claim['id'], $claim['at']);
    }

    /**
     * Read the envelope, and park a row the factory cannot read.
     *
     * An unreadable row never reaches the handler, so nothing settles it. The
     * claim lapses after the reservation timeout, the next worker reads the same
     * bytes, and nothing ends that. Parking ends it without destroying the
     * bytes, which are the only record of the job, the same way `RedisQueue`
     * holds an unreadable envelope on a list of its own.
     */
    protected static function decode(string $envelope, int|string $id): JobContract|null
    {
        try {
            return new JobFactory()->fromJson($envelope);
        } catch (JsonException|QueueMessageInvalidEnvelopeException) {
            static::park($id);

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
     * @throws QueueServerInvalidRowKeyException
     * @throws QueueServerNotConnectedException
     *
     * @return array{0: int|string, 1: string}|null
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

        // No value names this row, so every later statement would have to go
        // unqualified and take whichever row is at the head. The set it belongs
        // to is nameable even though the row is not, so the whole set is parked
        // at once and an operator reads it back the way a parked envelope reads.
        if ($id === null) {
            static::parkKeyless();

            return null;
        }

        // A key this entry cannot bind. One row cannot carry a type the column
        // does not have, so the column itself is wrong and no row on it will
        // ever run.
        if (! is_int($id) && ! is_string($id)) {
            throw new QueueServerInvalidRowKeyException(
                "The $table table keys on a type the queue cannot bind: " . get_debug_type($id)
            );
        }

        if (! is_string($envelope)) {
            // A select takes nothing off the table, so a row this method cannot
            // read stays at the queue head and is handed back on every poll.
            // `decode()` settled the policy for a row the adapter cannot read:
            // park it, because nothing else ends the loop. The key the select
            // returned is exact here, so the update names it rather than
            // repeating the predicate and taking whichever row is at the head.
            static::park($id);

            return null;
        }

        // The key goes back as it came. A driver hands a BIGINT back as text on
        // pgsql, and on mysql whenever it emulates prepares, so casting would
        // turn a key that is not all digits into 0 — and on mysql
        // `WHERE id = 0` matches every row with a non-numeric key. A table the
        // application owns may key on a UUID, and that job is runnable.
        return [$id, $envelope];
    }

    /**
     * Take ownership of a row, if no other worker took it first.
     *
     * The write is conditional, so two workers reading the same row cannot both
     * win: the second one updates nothing.
     *
     * @return int|null The stamp this worker claimed with, or null if it lost
     */
    protected static function claim(int|string $id): int|null
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

        // The stamp this worker wrote, so the settlement can fence on it. A
        // row count of zero means another worker claimed it first.
        return $statement->getRowCount() > 0
            ? $now
            : null;
    }

    /**
     * Hold a row out of the claim's reach without taking it off the table.
     *
     * Both stamps move. The reservation this far ahead never goes stale, so no
     * staleness window reaches back to it; the availability stamp moving with it
     * is what takes the row out of the range the claim index narrows to, so a
     * parked row stops joining the sort that every later poll pays for.
     */
    protected static function park(int|string $id): void
    {
        $table = static::$table;

        $statement = static::getConnection()->prepare(
            "UPDATE $table SET reserved_at_ms = :parkedReserved, available_at_ms = :parkedAvailable"
            . ' WHERE id = :id'
        );

        // One bound value per placeholder: a native prepare answers HY093 for
        // a name it sees twice and bound once
        $statement->bindValue(new Value('parkedReserved', self::PARKED_AT_MS));
        $statement->bindValue(new Value('parkedAvailable', self::PARKED_AT_MS));
        $statement->bindValue(new Value('id', $id));
        $statement->execute();
    }

    /**
     * Hold every row this queue cannot name out of the claim's reach.
     *
     * The predicate names the set rather than the row, which is the one write
     * a keyless row can be given. It is idempotent, so a queue that collects
     * several of them parks each one on the next poll that reaches one.
     *
     * @throws QueueServerNotConnectedException
     */
    protected static function parkKeyless(): void
    {
        $table = static::$table;

        $statement = static::getConnection()->prepare(
            "UPDATE $table SET reserved_at_ms = :parkedReserved, available_at_ms = :parkedAvailable"
            . ' WHERE queue = :queue AND id IS NULL'
        );

        $statement->bindValue(new Value('parkedReserved', self::PARKED_AT_MS));
        $statement->bindValue(new Value('parkedAvailable', self::PARKED_AT_MS));
        $statement->bindValue(new Value('queue', static::$queue));
        $statement->execute();
    }

    /**
     * Hand any reserved row back to the queue.
     */
    protected static function releaseCurrent(): void
    {
        $claim = static::$claim;

        if ($claim !== null) {
            static::$claim = null;
            $table         = static::$table;

            // Fenced on the claim, as the delete is: a worker whose window
            // lapsed would otherwise free a row another worker is running
            $statement = static::getConnection()->prepare(
                "UPDATE $table SET reserved_at_ms = NULL WHERE id = :id AND reserved_at_ms = :claimedAt"
            );

            $statement->bindValue(new Value('id', $claim['id']));
            $statement->bindValue(new Value('claimedAt', $claim['at']));
            $statement->execute();
        }
    }

    /**
     * Take a row off the table for good, if this worker still owns it.
     *
     * The claim stamp fences the delete. A worker whose window lapsed no
     * longer owns the row, and deleting it would take the copy that the worker
     * which reclaimed it is still running.
     */
    protected static function delete(int|string $id, int $claimedAt): void
    {
        $table = static::$table;

        $statement = static::getConnection()->prepare(
            "DELETE FROM $table WHERE id = :id AND reserved_at_ms = :claimedAt"
        );

        $statement->bindValue(new Value('id', $id));
        $statement->bindValue(new Value('claimedAt', $claimedAt));
        $statement->execute();
    }
}
