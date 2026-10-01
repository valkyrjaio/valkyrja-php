<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Log\Data;

use Valkyrja\Log\Data\Contract\LogPsrConfigContract;
use Valkyrja\Log\Data\LogPsrConfig;
use Valkyrja\Log\Enum\LogLevel;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class LogPsrConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(LogPsrConfigContract::class, new LogPsrConfig());
    }

    public function testDefaults(): void
    {
        $config = new LogPsrConfig();

        self::assertNull($config->psrName);
        self::assertNull($config->psrFilePath);
        self::assertSame(LogLevel::DEBUG, $config->psrLevel);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new LogPsrConfig(
            psrName: 'app',
            psrFilePath: '/tmp',
            psrLevel: LogLevel::WARNING,
        );

        self::assertSame('app', $config->psrName);
        self::assertSame('/tmp', $config->psrFilePath);
        self::assertSame(LogLevel::WARNING, $config->psrLevel);
    }
}
