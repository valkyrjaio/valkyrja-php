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
use Valkyrja\Queue\Client\Puller\AmqpPuller;

/**
 * Records the poll yield instead of actually sleeping through it.
 *
 * The yield is an irreducible wall-clock call, so it is driven through an
 * overridable seam rather than making the suite wait it out.
 */
final class AmqpPullerFixture extends AmqpPuller
{
    public int $waits = 0;

    /**
     * @inheritDoc
     */
    #[Override]
    protected function pause(int $seconds): void
    {
        $this->waits += $seconds;
    }
}
