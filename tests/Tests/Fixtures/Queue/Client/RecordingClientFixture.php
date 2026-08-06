<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Client;

use Override;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

/**
 * Records the hold a re-queuer supplies alongside each job.
 *
 * The in-memory client drops the hold, because an in-process adapter has
 * nowhere to keep a job waiting. Asserting the ramp needs the value itself, so
 * this keeps it.
 */
final class RecordingClientFixture extends Client
{
    /** @var int[] The hold supplied for each re-enqueue, in order */
    public array $delays = [];

    /**
     * @inheritDoc
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
        $this->delays[] = $delayMs;

        $this->publish($job);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function publish(JobContract $job): void
    {
    }
}
