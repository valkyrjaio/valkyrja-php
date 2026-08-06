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
use Valkyrja\Queue\Message\Job\Contract\JobContract;

use function array_shift;

/**
 * The zero-config default: run the job inline, now, blocking.
 *
 * It runs the job to completion, retries and all. There is no durable place to
 * hold a retry delay, so the delay is skipped and the incremented job re-runs
 * immediately until it acknowledges or exhausts its attempts. Only the *timing*
 * differs from production; the retry *count* is identical.
 *
 * Everything goes through the queue entry point, never the handler directly, so
 * the same routes, middleware, and config apply — swapping this for a real
 * broker is a config change with no code change, and the caller cannot tell
 * where a job ran.
 */
class SyncClient extends Client
{
    /** @var JobContract[] */
    protected array $buffer = [];

    protected bool $running = false;

    protected QueueConfigContract $config;

    /**
     * @param non-empty-string $version The framework version stamped into the provenance
     */
    public function __construct(
        QueueConfigContract|null $config = null,
        string $version = ApplicationInfo::VERSION,
    ) {
        $this->config = $config ?? new QueueConfig();

        parent::__construct(
            applicationName: $this->config->applicationName,
            version: $version,
        );
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
        } finally {
            $this->running = false;
            $this->buffer  = [];
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
        );
    }
}
