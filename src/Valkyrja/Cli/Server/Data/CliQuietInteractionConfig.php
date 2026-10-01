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
use Valkyrja\Cli\Server\Data\Contract\CliQuietInteractionConfigContract;

class CliQuietInteractionConfig implements CliQuietInteractionConfigContract
{
    /**
     * @param non-empty-string $quietOptionName      The name of the option that silences the output
     * @param non-empty-string $quietOptionShortName The short name of the option that silences the output
     */
    public function __construct(
        public readonly string $quietOptionName = OptionName::QUIET,
        public readonly string $quietOptionShortName = OptionShortName::QUIET,
    ) {
    }
}
