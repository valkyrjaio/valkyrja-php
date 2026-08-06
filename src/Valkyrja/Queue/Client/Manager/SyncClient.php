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
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Entry\Queue;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Client\Requeuer\Requeuer;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientSyncJobFailedException;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

use function array_shift;
use function sprintf;

/**
 * The zero-config default: run the job inline, now, blocking.
 *
 * It runs the job to completion, retries and all. There is no durable place to
 * hold a retry delay, so the delay is skipped and the incremented job re-runs
 * immediately until it acknowledges or exhausts its attempts. Only the *timing*
 * differs from production; the retry *count* is identical.
 *
 * A terminal failure throws at the call site, which no other client does. The
 * caller is still blocked on the push, so it is still there to be told.
 *
 * Everything goes through the queue entry point, never the handler directly, so
 * the same routes, middleware, and config apply — swapping this for a real
 * broker is a config change with no code change, and the caller cannot tell
 * where a job ran.
 */
class SyncClient extends Client implements RequeuerContract
{
    /** @var JobContract[] */
    protected array $buffer = [];

    protected bool $running = false;

    protected QueueConfigContract $config;

    protected RequeuerContract $requeuer;

    protected JobContract|null $failedJob = null;

    protected JobResult|null $failedResult = null;

    /**
     * @param non-empty-string $version The framework version stamped into the provenance
     */
    public function __construct(
        QueueConfigContract|null $config = null,
        string $version = ApplicationInfo::VERSION,
        RequeuerContract $requeuer = new Requeuer(),
    ) {
        $this->config   = $config ?? new QueueConfig();
        $this->requeuer = $requeuer;

        parent::__construct(
            applicationName: $this->config->applicationName,
            version: $version,
        );
    }

    /**
     * @inheritDoc
     *
     * The sync client settles its own outcomes so that it sees a terminal
     * failure. A retry still goes to the composed re-queuer, which hands the
     * incremented job back through push and so continues the loop above.
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $this->requeuer->settle($job, $result, $client);

        if ($result === JobResult::FAIL || $result === JobResult::DEAD_LETTER) {
            $this->failedJob    = $job;
            $this->failedResult = $result;
        }
    }

    /**
     * @inheritDoc
     *
     * The re-runs are looped rather than nested. A retry re-enters this method
     * from inside the entry, so recursing would both grow the stack with the
     * chain and finish each delivery inside-out — the last redelivery would
     * settle before the first. Buffering and looping keeps a job's outcomes in
     * the order they actually happened, which is what the result log reads.
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
     * Run a job through the isolated queue entry.
     *
     * The entry hands any retry back to this very client, whose publish runs it
     * again — which is what makes the retry chain loop here rather than needing
     * a durable hold.
     */
    protected function run(JobContract $job): void
    {
        Queue::run(
            config: $this->config,
            job: $job,
            client: $this,
            requeuer: $this,
        );
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
