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
use Valkyrja\Queue\Client\Puller\Contract\PullerContract;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

use function array_shift;

/**
 * Yields a scripted sequence of deliveries, then nothing.
 *
 * A null stands for a poll that timed out, which is what lets a test drive the
 * entry's loop through the branch where nothing arrived.
 */
final class PullerFixture implements PullerContract
{
    public bool $connected = false;

    public int $receiveCount = 0;

    /**
     * @param array<int, JobContract|null> $deliveries The scripted deliveries
     */
    public function __construct(
        private array $deliveries = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function connect(): void
    {
        $this->connected = true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function receive(): JobContract|null
    {
        $this->receiveCount++;

        return array_shift($this->deliveries);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        $this->connected = false;
    }
}
