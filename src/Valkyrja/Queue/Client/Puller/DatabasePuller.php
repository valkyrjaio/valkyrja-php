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

/**
 * Polls a database table, and settles against the reserved row.
 *
 * This is both the puller and the re-queuer, but for the opposite reason to the
 * AMQP and SQS adapters. Those own redelivery; a database does not. Here the
 * two are joined only because the reserved *row* must be removed on any
 * outcome, and only the object that claimed it knows its id. The retry itself
 * stays framework-owned: on a retry this hands straight over to the re-queuer,
 * which increments the attempt and applies the ramp, exactly as it does for
 * Redis.
 *
 * The table and its columns are described on the client.
 */
class DatabasePuller implements PullerContract, RequeuerContract
{
    /**
     * The id of the row currently reserved, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough — and
     * it is cleared on settlement so a second settle cannot act twice.
     */
    protected int|null $current = null;

    /**
     * @param non-empty-string $queue The queue jobs are consumed from
     * @param non-empty-string $table The table jobs are stored in
     */
    public function __construct(
        protected ManagerContract $manager,
        protected string $queue = 'default',
        protected string $table = DatabaseClient::DEFAULT_TABLE,
        protected RequeuerContract $requeuer = new Requeuer(),
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     *
     * The manager owns the connection, so there is nothing to open.
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
        $statement = $this->manager->prepare(
            "SELECT id, envelope FROM $this->table"
            . ' WHERE queue = :queue AND reserved_at_ms IS NULL AND available_at_ms <= :now'
            . ' ORDER BY priority DESC, id ASC LIMIT 1'
        );

        $statement->bindValue(new Value('queue', $this->queue));
        $statement->bindValue(new Value('now', $this->now()));
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
        $statement = $this->manager->prepare(
            "UPDATE $this->table SET reserved_at_ms = :now"
            . ' WHERE id = :id AND reserved_at_ms IS NULL'
        );

        $statement->bindValue(new Value('now', $this->now()));
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
