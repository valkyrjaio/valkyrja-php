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
     * The entry of a processor with no retry of its own calls this with the
     * job as dispatched, and the client authors all three:
     *
     * - the hold, derived from the dispatched job, ramped where the job says so
     * - the attempt count, incremented
     * - the modification time, stamped
     *
     * A processor that owns redelivery never reaches this method. The entry of
     * such a processor answers the retry from its own settlement instead.
     *
     * @param JobContract $job The job as dispatched, before any increment
     */
    public function requeue(JobContract $job): void;

    /**
     * Get the stamped jobs handed to this client during this unit of work.
     *
     * A redelivery is recorded as well, so a job appears once for each time it
     * was handed to this client.
     *
     * @return JobContract[]
     */
    public function getPushed(): array;

    /**
     * Drop the record, ending the unit of work it belongs to.
     *
     * A pull worker calls this before each job, so one job is its unit of work.
     * Every other host calls it at the end of whatever its own unit of work is,
     * because nothing else bounds the record.
     *
     * A host holds one client for its whole life, so the record has to end with
     * the unit of work rather than with the process.
     */
    public function clearPushed(): void;
}
