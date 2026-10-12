<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Caster\Contract;

use Valkyrja\Cli\Routing\Data\Contract\ParameterContract;

interface CasterContract
{
    /**
     * Get each value of a parameter, with the cast of the parameter applied to it.
     *
     * @param ParameterContract $parameter The parameter
     *
     * @return array<array-key, mixed>
     */
    public function getCastValues(ParameterContract $parameter): array;
}
