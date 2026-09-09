<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Handler\Contract;

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

interface JobHandlerContract
{
    /**
     * Handle a job through the router, mapping any throwable to an outcome.
     */
    public function handle(JobContract $job): JobResult;

    /**
     * Run the always-run stage before the adapter settles the outcome.
     */
    public function settlingResult(JobContract $job, JobResult $result): JobResult;

    /**
     * Run the always-run stage after the adapter has settled the outcome.
     */
    public function resultSettled(JobContract $job, JobResult $result): void;

    /**
     * Handle a job and run the pre-settlement stage, returning what the adapter must settle.
     */
    public function run(JobContract $job): JobResult;
}
