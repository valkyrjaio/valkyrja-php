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

use Valkyrja\Support\Time\Rfc3339;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class Rfc3339Test extends TestCase
{
    public function testRendersAUtcInstant(): void
    {
        self::assertSame('2026-01-16T13:19:58.000Z', Rfc3339::fromMilliseconds(1768569598000));
    }

    public function testKeepsMillisecondPrecision(): void
    {
        // The wire form pads to three digits, so a reader can compare two stamps as text
        self::assertSame('2026-01-16T13:19:58.007Z', Rfc3339::fromMilliseconds(1768569598007));
    }

    public function testRendersTheEpoch(): void
    {
        self::assertSame('1970-01-01T00:00:00.000Z', Rfc3339::fromMilliseconds(0));
    }
}
