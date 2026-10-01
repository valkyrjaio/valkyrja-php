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
use Valkyrja\Cli\Server\Data\Contract\CliVersionCommandConfigContract;

class CliVersionCommandConfig implements CliVersionCommandConfigContract
{
    /**
     * @param non-empty-string $versionCommandName     The name of the command that shows the version
     * @param non-empty-string $versionOptionName      The name of the option that shows the version
     * @param non-empty-string $versionOptionShortName The short name of the option that shows the version
     */
    public function __construct(
        public readonly string $versionCommandName = CommandName::VERSION,
        public readonly string $versionOptionName = OptionName::VERSION,
        public readonly string $versionOptionShortName = OptionShortName::VERSION,
    ) {
    }
}
