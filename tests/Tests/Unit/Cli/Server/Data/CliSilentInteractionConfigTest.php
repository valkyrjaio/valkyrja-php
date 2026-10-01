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
use Valkyrja\Cli\Server\Data\CliSilentInteractionConfig;
use Valkyrja\Cli\Server\Data\Contract\CliSilentInteractionConfigContract;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CliSilentInteractionConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(CliSilentInteractionConfigContract::class, new CliSilentInteractionConfig());
    }

    public function testDefaults(): void
    {
        $config = new CliSilentInteractionConfig();

        self::assertSame(OptionName::SILENT, $config->silentOptionName);
        self::assertSame(OptionShortName::SILENT, $config->silentOptionShortName);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new CliSilentInteractionConfig(
            silentOptionName: 'mute',
            silentOptionShortName: 'M',
        );

        self::assertSame('mute', $config->silentOptionName);
        self::assertSame('M', $config->silentOptionShortName);
    }
}
