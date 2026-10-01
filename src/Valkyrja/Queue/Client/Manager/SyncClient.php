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

use Override;
use Valkyrja\Queue\Client\Manager\Abstract\InternalClient;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientSyncJobFailedException;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

use function array_shift;
use function sprintf;

class SyncClient extends InternalClient
{
    /** @var JobContract[] */
    protected array $buffer = [];

    protected bool $running = false;

    protected JobContract|null $failedJob = null;

    protected JobResult|null $failedResult = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result): void
    {
        parent::settle($job, $result);

        // The first failure is the one the caller pushed, so a later failure
        // from a job that this one pushed must not replace it
        if ($result->isDeadLettered() && $this->failedResult === null) {
            $this->failedJob    = $job;
            $this->failedResult = $result;
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function publish(JobContract $job): void
    {
        $this->buffer[] = $job;

        if ($this->running) {
            return;
        }

        $this->running = true;

        try {
            while (($next = array_shift($this->buffer)) !== null) {
                // Settling through this client shows it the terminal outcome,
                // and puts a retry back in the buffer that this loop drains
                $this->run($next);
            }

            $this->throwOnFailure();
        } finally {
            $this->running      = false;
            $this->buffer       = [];
            $this->failedJob    = null;
            $this->failedResult = null;
        }
    }

    /**
     * Surface a terminal failure at the call site.
     *
     * The whole buffer drains first, so a job that pushed another job still
     * runs it. Only then does the failure throw.
     *
     * @throws QueueClientSyncJobFailedException
     */
    protected function throwOnFailure(): void
    {
        $job    = $this->failedJob;
        $result = $this->failedResult;

        if ($job === null || $result === null) {
            return;
        }

        throw new QueueClientSyncJobFailedException(
            sprintf(
                'Job "%s" (%s) ended in %s after %d attempt(s).',
                $job->getName(),
                $job->getId(),
                $result->name,
                $job->getAttempts(),
            )
        );
    }
}
