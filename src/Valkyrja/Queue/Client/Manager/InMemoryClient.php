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
use Valkyrja\Queue\Client\Manager\Contract\InternalClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

class InMemoryClient extends Client implements InternalClientContract
{
    /** @var JobContract[] */
    protected array $buffer = [];

    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result): void
    {
        // Nothing holds this job but the buffer, so a retry goes back in it
        if ($result === JobResult::RETRY) {
            $this->requeue($job);
        }
    }

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
