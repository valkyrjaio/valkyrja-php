<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Puller;

use JsonException;
use Override;
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DatabaseClient;
use Valkyrja\Queue\Client\Puller\Contract\PullerContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Client\Requeuer\Requeuer;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Support\Time\Microtime;

use function is_int;
use function is_string;

class DatabasePuller implements PullerContract, RequeuerContract
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
     * The id of the row currently reserved, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough — and
     * it is cleared on settlement so a second settle cannot act twice.
     */
    protected int|null $current = null;

    /**
     * @param non-empty-string $queue                The queue jobs are consumed from
     * @param non-empty-string $table                The table jobs are stored in
     * @param int<1, max>      $reservationTimeoutMs The age at which a claim is abandoned
     */
    public function __construct(
        protected ManagerContract $manager,
        protected string $queue = 'default',
        protected string $table = DatabaseClient::DEFAULT_TABLE,
        protected int $reservationTimeoutMs = self::DEFAULT_RESERVATION_TIMEOUT_MS,
        protected RequeuerContract $requeuer = new Requeuer(),
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function connect(): void
    {
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function receive(): JobContract|null
    {
        $row = $this->findEligible();

        if ($row === null) {
            return null;
        }

        [$id, $envelope] = $row;

        if (! $this->claim($id)) {
            // Another worker claimed the row between the read and the write.
            // The loop asks again rather than this one waiting for a winner.
            return null;
        }

        $this->current = $id;

        return $this->factory->fromJson($envelope);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        // Anything still reserved was not completed, so hand it back rather
        // than leaving a row no worker will ever claim again
        $this->releaseCurrent();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $id = $this->current;

        if ($id === null) {
            return;
        }

        $this->current = null;

        // The reserved row is spent whatever the outcome: a retry arrives as a
        // fresh row carrying the incremented attempt count
        $this->delete($id);

        $this->requeuer->settle($job, $result, $client);
    }

    /**
     * Find the next job whose hold has elapsed and that no worker holds.
     *
     * @throws JsonException
     *
     * @return array{0: int, 1: string}|null
     */
    protected function findEligible(): array|null
    {
        $now = $this->now();

        $statement = $this->manager->prepare(
            "SELECT id, envelope FROM $this->table"
            . ' WHERE queue = :queue AND available_at_ms <= :now'
            . ' AND (reserved_at_ms IS NULL OR reserved_at_ms <= :stale)'
            . ' ORDER BY priority DESC, id ASC LIMIT 1'
        );

        $statement->bindValue(new Value('queue', $this->queue));
        $statement->bindValue(new Value('now', $now));
        $statement->bindValue(new Value('stale', $now - $this->reservationTimeoutMs));
        $statement->execute();

        // fetchAll, not fetch: the ORM treats an empty result as an error, and
        // an empty queue is the normal case here rather than a failure
        $row = $statement->fetchAll()[0] ?? [];

        $id       = $row['id'] ?? null;
        $envelope = $row['envelope'] ?? null;

        if (! is_string($envelope) || (! is_int($id) && ! is_string($id))) {
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
    protected function claim(int $id): bool
    {
        $now = $this->now();

        $statement = $this->manager->prepare(
            "UPDATE $this->table SET reserved_at_ms = :now"
            . ' WHERE id = :id AND (reserved_at_ms IS NULL OR reserved_at_ms <= :stale)'
        );

        $statement->bindValue(new Value('now', $now));
        $statement->bindValue(new Value('stale', $now - $this->reservationTimeoutMs));
        $statement->bindValue(new Value('id', $id));
        $statement->execute();

        return $statement->getRowCount() > 0;
    }

    /**
     * Hand any reserved row back to the queue.
     */
    protected function releaseCurrent(): void
    {
        $id = $this->current;

        if ($id !== null) {
            $this->current = null;

            $statement = $this->manager->prepare(
                "UPDATE $this->table SET reserved_at_ms = NULL WHERE id = :id"
            );

            $statement->bindValue(new Value('id', $id));
            $statement->execute();
        }
    }

    /**
     * Take a row off the table for good.
     */
    protected function delete(int $id): void
    {
        $statement = $this->manager->prepare("DELETE FROM $this->table WHERE id = :id");

        $statement->bindValue(new Value('id', $id));
        $statement->execute();
    }

    /**
     * Get the current time in epoch milliseconds.
     *
     * @return int<0, max>
     */
    protected function now(): int
    {
        $now = (int) (Microtime::get() * 1000.0);

        return $now > 0
            ? $now
            : 0;
    }
}
