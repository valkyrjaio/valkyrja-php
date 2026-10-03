<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Manager\Contract;

use Valkyrja\Queue\Message\Job\Contract\JobContract;

interface ClientContract
{
    /**
     * Enqueue a fresh job.
     */
    public function push(JobContract $job): void;

    /**
     * Re-enqueue an already incremented job for a retry.
     *
     * @param JobContract $job     The incremented copy, not the dispatched job
     * @param int<0, max> $delayMs The hold before the job becomes eligible again
     */
    public function retry(JobContract $job, int $delayMs): void;

    /**
     * Re-enqueue a job for its next attempt.
     *
     * This bumps the attempt count and derives the hold from the ramp of the
     * attempt that just failed. A processor without native redelivery settles
     * a retry by calling this.
     *
     * @param JobContract $job The job as dispatched, before any increment
     */
    public function requeue(JobContract $job): void;

    /**
     * Get the stamped jobs handed to this client during this unit of work.
     *
     * A redelivery is recorded, so a job appears once for each delivery that
     * this client enqueued.
     *
     * @return JobContract[]
     */
    public function getPushed(): array;

    /**
     * Drop the record, ending the unit of work it belongs to.
     *
     * A long-running worker holds one client for its whole life, so the record
     * has to end with each job rather than with the process.
     */
    public function clearPushed(): void;
}
