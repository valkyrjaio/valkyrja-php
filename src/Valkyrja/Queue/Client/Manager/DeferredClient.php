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

class DeferredClient extends Client
{
    /** @var JobContract[] */
    protected array $buffer = [];

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
     * Run everything buffered, emptying the buffer.
     *
     * The bridge middleware calls this once the host has finished its response.
     */
    public function drain(): void
    {
        // A retry re-buffers, so the drain keeps going until nothing is left:
        // once the response is out there is no later moment to finish in. The
        // attempt ceiling is what terminates the loop.
        while ($this->buffer !== []) {
            $buffered = $this->buffer;

            $this->buffer = [];

            foreach ($buffered as $job) {
                $this->run($job);
            }
        }
    }

    /**
     * Get everything buffered without emptying the buffer.
     *
     * @return JobContract[]
     */
    public function getBuffered(): array
    {
        return $this->buffer;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function publish(JobContract $job): void
    {
        $this->buffer[] = $job;
    }

    /**
     * Run a job through the isolated queue entry.
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
