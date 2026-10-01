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
use Valkyrja\Cli\Server\Data\CliHelpCommandConfig;
use Valkyrja\Cli\Server\Data\Contract\CliHelpCommandConfigContract;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CliHelpCommandConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(CliHelpCommandConfigContract::class, new CliHelpCommandConfig());
    }

    public function testDefaults(): void
    {
        $config = new CliHelpCommandConfig();

        self::assertSame(CommandName::HELP, $config->helpCommandName);
        self::assertSame(OptionName::HELP, $config->helpOptionName);
        self::assertSame(OptionShortName::HELP, $config->helpOptionShortName);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new CliHelpCommandConfig(
            helpCommandName: 'assist',
            helpOptionName: 'assist',
            helpOptionShortName: 'a',
        );

        self::assertSame('assist', $config->helpCommandName);
        self::assertSame('assist', $config->helpOptionName);
        self::assertSame('a', $config->helpOptionShortName);
    }
}
