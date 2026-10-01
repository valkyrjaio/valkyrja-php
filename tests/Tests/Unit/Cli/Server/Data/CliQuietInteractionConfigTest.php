<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cli\Server\Data;

use Valkyrja\Cli\Routing\Constant\OptionName;
use Valkyrja\Cli\Routing\Constant\OptionShortName;
use Valkyrja\Cli\Server\Data\CliQuietInteractionConfig;
use Valkyrja\Cli\Server\Data\Contract\CliQuietInteractionConfigContract;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CliQuietInteractionConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(CliQuietInteractionConfigContract::class, new CliQuietInteractionConfig());
    }

    public function testDefaults(): void
    {
        $config = new CliQuietInteractionConfig();

        self::assertSame(OptionName::QUIET, $config->quietOptionName);
        self::assertSame(OptionShortName::QUIET, $config->quietOptionShortName);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new CliQuietInteractionConfig(
            quietOptionName: 'hush',
            quietOptionShortName: 'H',
        );

        self::assertSame('hush', $config->quietOptionName);
        self::assertSame('H', $config->quietOptionShortName);
    }
}
