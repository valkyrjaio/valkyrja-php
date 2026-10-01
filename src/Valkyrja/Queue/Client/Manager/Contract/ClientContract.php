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
     * @param int<0, max> $delayMs The hold before the job becomes eligible again
     */
    public function retry(JobContract $job, int $delayMs): void;

    /**
     * Re-enqueue a job for its next attempt.
     *
     * Bumps the attempt count and holds the job for the ramp of the attempt
     * that just failed, so a processor without native redelivery settles a
     * retry by calling this.
     */
    public function requeue(JobContract $job): void;

    /**
     * Get the stamped jobs handed to this client during this unit of work.
     *
     * @return JobContract[]
     */
    public function getPushed(): array;
}
