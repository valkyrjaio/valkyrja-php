<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Attribute\Route;

use Attribute;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;

#[Attribute(Attribute::TARGET_METHOD)]
class Handler
{
    /** @var callable(ContainerContract, RouteContract):OutputContract */
    public $handler;

    /**
     * @param callable(ContainerContract, RouteContract):OutputContract $handler
     */
    public function __construct(
        callable $handler,
    ) {
        $this->handler = $handler;
    }
}
