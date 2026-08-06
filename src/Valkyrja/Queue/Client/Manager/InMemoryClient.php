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
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

/**
 * The test adapter: push records, a test drains.
 *
 * Distinct from the sync adapter, which runs the job now — this one holds jobs
 * until you process them, so a test can assert on what was enqueued before
 * deciding whether to run any of it.
 */
class InMemoryClient extends Client
{
    /** @var JobContract[] */
    protected array $buffer = [];

    /**
     * Take everything buffered, emptying the buffer.
     *
     * @return JobContract[]
     */
    public function drain(): array
    {
        $buffered = $this->buffer;

        $this->buffer = [];

        return $buffered;
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
}
