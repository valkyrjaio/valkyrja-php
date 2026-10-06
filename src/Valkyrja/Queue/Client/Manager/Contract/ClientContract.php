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
     * Settle a retry for an already incremented job.
     *
     * @param JobContract $job     The incremented copy, not the dispatched job
     * @param int<0, max> $delayMs The hold before the job becomes eligible again
     */
    public function retry(JobContract $job, int $delayMs): void;

    /**
     * Settle a retry for the job as dispatched.
     *
     * The framework calls this with the job as dispatched, and this derives the
     * hold from that job. The ramp applies only where the job sets it. A client
     * that owns redelivery increments the attempt count and stamps the
     * modification time. A client whose processor counts the attempt hands that
     * processor the retry signal, and authors neither field.
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
     * The framework calls this, and application code never does. The call comes
     * once the unit of work is over:
     *
     * - A buffering client is cleared once a drain has run every recorded job.
     * - Every other client is cleared at the end of the request, the command,
     *   or the one job a worker just ran.
     *
     * A long-running worker holds one client for its whole life, so the record
     * has to end with each job rather than with the process.
     */
    public function clearPushed(): void;
}
