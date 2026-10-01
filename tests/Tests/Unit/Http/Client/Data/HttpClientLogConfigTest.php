<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Http\Client\Data;

use Valkyrja\Http\Client\Data\Contract\HttpClientLogConfigContract;
use Valkyrja\Http\Client\Data\HttpClientLogConfig;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\PsrLogger;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class HttpClientLogConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(HttpClientLogConfigContract::class, new HttpClientLogConfig());
    }

    public function testDefaults(): void
    {
        self::assertSame(LoggerContract::class, new HttpClientLogConfig()->httpClientLogLogger);
    }

    public function testCustomValuesAreStored(): void
    {
        self::assertSame(PsrLogger::class, new HttpClientLogConfig(httpClientLogLogger: PsrLogger::class)->httpClientLogLogger);
    }
}
