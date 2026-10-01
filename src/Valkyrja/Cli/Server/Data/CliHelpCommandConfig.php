<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Server\Data;

use Valkyrja\Cli\Routing\Constant\OptionName;
use Valkyrja\Cli\Routing\Constant\OptionShortName;
use Valkyrja\Cli\Server\Constant\CommandName;
use Valkyrja\Cli\Server\Data\Contract\CliHelpCommandConfigContract;

class CliHelpCommandConfig implements CliHelpCommandConfigContract
{
    /**
     * @param non-empty-string $helpCommandName     The name of the command that shows the help output
     * @param non-empty-string $helpOptionName      The name of the option that shows the help output
     * @param non-empty-string $helpOptionShortName The short name of the option that shows the help output
     */
    public function __construct(
        public readonly string $helpCommandName = CommandName::HELP,
        public readonly string $helpOptionName = OptionName::HELP,
        public readonly string $helpOptionShortName = OptionShortName::HELP,
    ) {
    }
}
