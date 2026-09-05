<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Middleware\ThrowableCaught;

use Override;
use Throwable;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerWorkerShutdownException;
use Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable;

class RetryPolicyThrowableCaughtMiddleware implements ThrowableCaughtMiddlewareContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function throwableCaught(
        JobContract $job,
        JobResult $result,
        Throwable $throwable,
        ThrowableCaughtHandlerContract $handler
    ): JobResult {
        return $handler->throwableCaught($job, $this->mapThrowable($job, $throwable), $throwable);
    }

    /**
     * Map a throwable to the outcome it should settle as.
     */
    protected function mapThrowable(JobContract $job, Throwable $throwable): JobResult
    {
        // A shutdown did not complete the work, so the job returns for another
        // worker without spending an attempt
        if ($throwable instanceof QueueServerWorkerShutdownException) {
            return JobResult::RETRY;
        }

        // Retrying this reproduces the same failure, so give up now
        if ($throwable instanceof QueueNonRetryableThrowable) {
            return JobResult::FAIL;
        }

        return $this->hasAttemptsRemaining($job)
            ? JobResult::RETRY
            : JobResult::DEAD_LETTER;
    }

    /**
     * Determine whether the job may be delivered again.
     */
    protected function hasAttemptsRemaining(JobContract $job): bool
    {
        return $job->getAttempts() < $job->getMaxAttempts();
    }
}
