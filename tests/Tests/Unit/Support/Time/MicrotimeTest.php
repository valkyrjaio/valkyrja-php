<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Support\Time;

use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function usleep;

/**
 * Test the Microtime support class.
 */
final class MicrotimeTest extends TestCase
{
    public function testFreezeWithCurrentTime(): void
    {
        $time = Microtime::get();

        Microtime::freeze($time);

        usleep(100);

        self::assertSame($time, Microtime::get());

        Microtime::unfreeze();
    }

    public function testFreezeWithCustomTime(): void
    {
        Microtime::freeze(5);
        $time = Microtime::get();

        usleep(100);

        self::assertSame($time, Microtime::get());

        Microtime::unfreeze();
    }

    public function testNowReadsTheClockInMilliseconds(): void
    {
        Microtime::freeze(1768564798.25);

        self::assertSame(1768564798250, Microtime::now());

        Microtime::unfreeze();
    }

    public function testNowFloorsAtZero(): void
    {
        // A frozen time before the epoch would otherwise give a negative stamp
        Microtime::freeze(-1.0);

        self::assertSame(0, Microtime::now());

        Microtime::unfreeze();
    }
}
