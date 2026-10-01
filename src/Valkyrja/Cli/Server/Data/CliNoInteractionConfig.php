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
use Valkyrja\Cli\Server\Data\Contract\CliNoInteractionConfigContract;

class CliNoInteractionConfig implements CliNoInteractionConfigContract
{
    /**
     * @param non-empty-string $noInteractionOptionName      The name of the option that turns interaction off
     * @param non-empty-string $noInteractionOptionShortName The short name of the option that turns interaction off
     */
    public function __construct(
        public readonly string $noInteractionOptionName = OptionName::NO_INTERACTION,
        public readonly string $noInteractionOptionShortName = OptionShortName::NO_INTERACTION,
    ) {
    }
}
