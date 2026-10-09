<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Caster;

use Override;
use Valkyrja\Cli\Routing\Caster\Contract\CasterContract;
use Valkyrja\Cli\Routing\Data\Contract\ParameterContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Type\Constant\CastArgument;

class Caster implements CasterContract
{
    public function __construct(
        protected ContainerContract $container,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCastValues(ParameterContract $parameter): array
    {
        $values = $parameter->getValues();

        if (!$parameter->hasCast()) {
            return $values;
        }

        $cast       = $parameter->getCast();
        $castValues = [];

        foreach ($values as $value) {
            $type = $this->container->getService($cast->type, [CastArgument::VALUE => $value]);

            $castValues[] = $cast->convert ? $type->asValue() : $type;
        }

        return $castValues;
    }
}
