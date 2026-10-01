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
use Valkyrja\Cli\Server\Data\Contract\CliSilentInteractionConfigContract;

class CliSilentInteractionConfig implements CliSilentInteractionConfigContract
{
    /**
     * @param non-empty-string $silentOptionName      The name of the option that silences every output
     * @param non-empty-string $silentOptionShortName The short name of the option that silences every output
     */
    public function __construct(
        public readonly string $silentOptionName = OptionName::SILENT,
        public readonly string $silentOptionShortName = OptionShortName::SILENT,
    ) {
    }
}
