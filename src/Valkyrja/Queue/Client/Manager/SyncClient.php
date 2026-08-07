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
