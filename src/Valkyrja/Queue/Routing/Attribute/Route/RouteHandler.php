<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Attribute\Route;

use Attribute;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

#[Attribute(Attribute::TARGET_METHOD)]
class RouteHandler
{
    /** @var callable(ContainerContract, RouteContract):JobResult */
    public $handler;

    /**
     * @param callable(ContainerContract, RouteContract):JobResult $handler
     */
    public function __construct(
        callable $handler,
    ) {
        $this->handler = $handler;
    }
}
