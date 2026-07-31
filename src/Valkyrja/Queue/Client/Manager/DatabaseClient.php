<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Manager;

use JsonException;
use Override;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

/**
 * Publishes to a database table.
 *
 * A database has no native retry, so this is a re-queue processor, like Redis:
 * the framework owns redelivery, and the envelope's attempt count and
 * modification time are authoritative. A held job carries the instant it
 * becomes eligible, so a hold costs a comparison rather than a second table.
 *
 * The table is the application's to create, because its migration tooling owns
 * the schema. The columns this adapter reads and writes are:
 *
 * ```sql
 * CREATE TABLE queue_jobs (
 *     id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *     queue           VARCHAR(255)    NOT NULL,
 *     envelope        LONGTEXT        NOT NULL,
 *     priority        INT             NOT NULL DEFAULT 0,
 *     available_at_ms BIGINT          NOT NULL,
 *     reserved_at_ms  BIGINT          NULL,
 *     INDEX queue_jobs_claim (queue, reserved_at_ms, available_at_ms, priority)
 * );
 * ```
 */
class DatabaseClient extends Client
{
    /** @var non-empty-string */
    public const string DEFAULT_TABLE = 'queue_jobs';

    /**
     * @param non-empty-string $queue           The queue jobs are published to
     * @param non-empty-string $table           The table jobs are stored in
     * @param non-empty-string $applicationName The application name stamped into the provenance
     * @param non-empty-string $version         The framework version stamped into the provenance
     */
    public function __construct(
        protected ManagerContract $manager,
        protected string $queue = 'default',
        protected string $table = self::DEFAULT_TABLE,
        string $applicationName = 'valkyrja',
        string $version = ApplicationInfo::VERSION,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
        parent::__construct(
            applicationName: $applicationName,
            version: $version,
        );
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    protected function publish(JobContract $job): void
    {
        $this->insert($job, $job->getDelayMs());
    }

    /**
     * @inheritDoc
     *
     * A database has no native retry, so this is a re-queue processor: the hold
     * comes from the re-queuer, which keyed it to the attempt that just failed.
     * The producer's original delay is intent recorded at first publish and
     * never re-fires.
     *
     * @throws JsonException
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
        $this->insert($job, $delayMs);
    }

    /**
     * Write one job onto the table.
     *
     * @param int<0, max> $delayMs The hold in milliseconds
     *
     * @throws JsonException
     */
    protected function insert(JobContract $job, int $delayMs): void
    {
        $statement = $this->manager->prepare(
            "INSERT INTO $this->table (queue, envelope, priority, available_at_ms, reserved_at_ms)"
            . ' VALUES (:queue, :envelope, :priority, :available_at_ms, NULL)'
        );

        $statement->bindValue(new Value('queue', $this->queue));
        $statement->bindValue(new Value('envelope', $this->factory->toJson($job)));
        $statement->bindValue(new Value('priority', $job->getPriority()));
        $statement->bindValue(new Value('available_at_ms', $this->now() + $delayMs));

        $statement->execute();
    }
}
