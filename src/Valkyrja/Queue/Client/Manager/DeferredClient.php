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
use Valkyrja\Queue\Message\Job\Contract\JobContract;

class DeferredClient extends InternalClient
{
    /** @var JobContract[] */
    protected array $buffer = [];

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
                $this->run($job, $this->requeuer);
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
}
