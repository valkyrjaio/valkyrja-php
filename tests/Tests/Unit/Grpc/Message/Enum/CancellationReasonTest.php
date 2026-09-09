<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Enum;

use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CancellationReasonTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame('client-cancelled', CancellationReason::CLIENT_CANCELLED->value);
        self::assertSame('deadline-exceeded', CancellationReason::DEADLINE_EXCEEDED->value);
        self::assertCount(2, CancellationReason::cases());
    }
}
