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
use Valkyrja\Cli\Server\Constant\CommandName;
use Valkyrja\Cli\Server\Data\CliVersionCommandConfig;
use Valkyrja\Cli\Server\Data\Contract\CliVersionCommandConfigContract;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CliVersionCommandConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(CliVersionCommandConfigContract::class, new CliVersionCommandConfig());
    }

    public function testDefaults(): void
    {
        $config = new CliVersionCommandConfig();

        self::assertSame(CommandName::VERSION, $config->versionCommandName);
        self::assertSame(OptionName::VERSION, $config->versionOptionName);
        self::assertSame(OptionShortName::VERSION, $config->versionOptionShortName);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new CliVersionCommandConfig(
            versionCommandName: 'release',
            versionOptionName: 'release',
            versionOptionShortName: 'r',
        );

        self::assertSame('release', $config->versionCommandName);
        self::assertSame('release', $config->versionOptionName);
        self::assertSame('r', $config->versionOptionShortName);
    }
}
