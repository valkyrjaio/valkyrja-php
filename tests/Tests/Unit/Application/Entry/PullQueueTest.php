<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry;

use Override;
use Valkyrja\Application\Entry\PullQueue;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the PullQueue entry's loop bounds.
 *
 * The bounds are what let a supervisor cycle the process for memory hygiene
 * rather than trusting it to run forever.
 */
final class PullQueueTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(1000.0);
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testAnUnboundedLoopNeverStops(): void
    {
        self::assertFalse(PullQueue::shouldStop(handled: 1_000_000, maxJobs: 0, deadline: 0.0));
    }

    public function testTheJobBoundStopsTheLoop(): void
    {
        self::assertFalse(PullQueue::shouldStop(handled: 1, maxJobs: 2, deadline: 0.0));
        self::assertTrue(PullQueue::shouldStop(handled: 2, maxJobs: 2, deadline: 0.0));
        self::assertTrue(PullQueue::shouldStop(handled: 3, maxJobs: 2, deadline: 0.0));
    }

    public function testTheTimeBoundStopsTheLoop(): void
    {
        self::assertFalse(PullQueue::shouldStop(handled: 0, maxJobs: 0, deadline: 1001.0));
        self::assertTrue(PullQueue::shouldStop(handled: 0, maxJobs: 0, deadline: 1000.0));
        self::assertTrue(PullQueue::shouldStop(handled: 0, maxJobs: 0, deadline: 999.0));
    }

    public function testEitherBoundIsEnough(): void
    {
        self::assertTrue(PullQueue::shouldStop(handled: 5, maxJobs: 5, deadline: 9999.0));
        self::assertTrue(PullQueue::shouldStop(handled: 0, maxJobs: 5, deadline: 999.0));
    }
}
