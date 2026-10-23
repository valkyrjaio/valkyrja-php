<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Attribute\Route;

use Attribute;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;

#[Attribute(Attribute::TARGET_METHOD)]
class Handler
{
    /** @var callable(ContainerContract, RouteContract):ResponseContract */
    public $handler;

    /**
     * @param callable(ContainerContract, RouteContract $route):ResponseContract $handler
     */
    public function __construct(
        callable $handler,
    ) {
        $this->handler = $handler;
    }
}
