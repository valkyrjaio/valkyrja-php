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
     * @param JobContract $job The job as dispatched, before any increment
     */
    public function requeue(JobContract $job): void;

    /**
     * Get the stamped jobs handed to this client during this unit of work.
     *
     * @return JobContract[]
     */
    public function getPushed(): array;

    /**
     * Drop the record, ending the unit of work it belongs to.
     */
    public function clearPushed(): void;
}
