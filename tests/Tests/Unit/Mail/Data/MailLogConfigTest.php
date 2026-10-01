<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Mail\Data;

use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\PsrLogger;
use Valkyrja\Mail\Data\Contract\MailLogConfigContract;
use Valkyrja\Mail\Data\MailLogConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class MailLogConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(MailLogConfigContract::class, new MailLogConfig());
    }

    public function testDefaults(): void
    {
        self::assertSame(LoggerContract::class, new MailLogConfig()->mailLogLogger);
    }

    public function testCustomValuesAreStored(): void
    {
        self::assertSame(PsrLogger::class, new MailLogConfig(mailLogLogger: PsrLogger::class)->mailLogLogger);
    }
}
