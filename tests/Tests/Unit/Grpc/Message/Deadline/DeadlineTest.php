<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Deadline;

use Override;
use Valkyrja\Grpc\Message\Deadline\Deadline;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use const PHP_FLOAT_MAX;

final class DeadlineTest extends TestCase
{
    /**
     * @inheritDoc
     */
    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testNone(): void
    {
        $deadline = Deadline::none();

        self::assertFalse($deadline->hasDeadline());
        self::assertFalse($deadline->isExpired());
        self::assertSame(Deadline::INFINITE_REMAINING, $deadline->getRemaining());
        self::assertSame(PHP_FLOAT_MAX, $deadline->getAbsoluteTime());
    }

    public function testDefaultConstructionIsNone(): void
    {
        self::assertFalse(new Deadline()->hasDeadline());
    }

    public function testFromTimeout(): void
    {
        Microtime::freeze(1000.0);

        $deadline = Deadline::fromTimeout(5.0);

        self::assertTrue($deadline->hasDeadline());
        self::assertSame(1005.0, $deadline->getAbsoluteTime());
        self::assertSame(5.0, $deadline->getRemaining());
        self::assertFalse($deadline->isExpired());
    }

    public function testFromAbsolute(): void
    {
        Microtime::freeze(1000.0);

        $deadline = Deadline::fromAbsolute(1010.0);

        self::assertTrue($deadline->hasDeadline());
        self::assertSame(1010.0, $deadline->getAbsoluteTime());
        self::assertSame(10.0, $deadline->getRemaining());
    }

    public function testRemainingClampsToZeroOnceElapsed(): void
    {
        Microtime::freeze(1000.0);

        $deadline = Deadline::fromTimeout(5.0);

        Microtime::freeze(1010.0);

        self::assertSame(0.0, $deadline->getRemaining());
        self::assertTrue($deadline->isExpired());
    }

    public function testExpiresExactlyAtTheAbsoluteTime(): void
    {
        Microtime::freeze(1000.0);

        $deadline = Deadline::fromAbsolute(1000.0);

        self::assertTrue($deadline->isExpired());
    }
}
