<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Session\Data;

use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\PsrLogger;
use Valkyrja\Session\Data\Contract\SessionLogConfigContract;
use Valkyrja\Session\Data\SessionLogConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class SessionLogConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(SessionLogConfigContract::class, new SessionLogConfig());
    }

    public function testDefaults(): void
    {
        self::assertSame(LoggerContract::class, new SessionLogConfig()->sessionLogLogger);
    }

    public function testCustomValuesAreStored(): void
    {
        self::assertSame(PsrLogger::class, new SessionLogConfig(sessionLogLogger: PsrLogger::class)->sessionLogLogger);
    }
}
