<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Requeuer;

use Override;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Support\Time\Microtime;

class Requeuer implements RequeuerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        if ($result !== JobResult::RETRY) {
            // Acknowledged, failed, and dead-lettered jobs are all terminal:
            // there is nothing to hand back to the processor
            return;
        }

        // The hold is read from the dispatched job, before the increment: the
        // ramp is keyed to the attempt that just failed, so taking it from the
        // incremented copy would make every hold one step too long
        $client->retry($this->increment($job), $job->getRetryDelayForAttemptMs());
    }

    /**
     * Mint the job the next delivery will carry.
     */
    protected function increment(JobContract $job): JobContract
    {
        return $job
            ->withAttempts($job->getAttempts() + 1)
            ->withModifiedAtMs($this->now());
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
