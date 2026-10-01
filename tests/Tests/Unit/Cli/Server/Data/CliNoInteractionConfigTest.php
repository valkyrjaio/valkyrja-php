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
use Valkyrja\Cli\Server\Data\CliNoInteractionConfig;
use Valkyrja\Cli\Server\Data\Contract\CliNoInteractionConfigContract;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CliNoInteractionConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(CliNoInteractionConfigContract::class, new CliNoInteractionConfig());
    }

    public function testDefaults(): void
    {
        $config = new CliNoInteractionConfig();

        self::assertSame(OptionName::NO_INTERACTION, $config->noInteractionOptionName);
        self::assertSame(OptionShortName::NO_INTERACTION, $config->noInteractionOptionShortName);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new CliNoInteractionConfig(
            noInteractionOptionName: 'batch',
            noInteractionOptionShortName: 'B',
        );

        self::assertSame('batch', $config->noInteractionOptionName);
        self::assertSame('B', $config->noInteractionOptionShortName);
    }
}
