<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Sms\Data;

use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\PsrLogger;
use Valkyrja\Sms\Data\Contract\SmsLogConfigContract;
use Valkyrja\Sms\Data\SmsLogConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class SmsLogConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(SmsLogConfigContract::class, new SmsLogConfig());
    }

    public function testDefaults(): void
    {
        self::assertSame(LoggerContract::class, new SmsLogConfig()->smsLogLogger);
    }

    public function testCustomValuesAreStored(): void
    {
        self::assertSame(PsrLogger::class, new SmsLogConfig(smsLogLogger: PsrLogger::class)->smsLogLogger);
    }
}
