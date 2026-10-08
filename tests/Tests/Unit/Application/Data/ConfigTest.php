<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Data;

use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Provider\ApplicationComponentProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the Config service.
 */
final class ConfigTest extends TestCase
{
    public function testDefault(): void
    {
        $data = new Config();

        self::assertSame('production', $data->environment);
        self::assertSame(ApplicationInfo::VERSION, $data->version);
        self::assertFalse($data->debugMode);
        self::assertNotEmpty($data->providers);
        self::assertInstanceOf(ApplicationComponentProvider::class, $data->providers[0]);
        self::assertSame('UTC', $data->timezone);
        self::assertSame('valkyrja', $data->applicationName);
    }
}
