<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Attribute\Method;

use Attribute;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

#[Attribute(Attribute::TARGET_METHOD)]
class MethodHandler
{
    /** @var callable(ContainerContract, RouteContract):ServiceResponseContract */
    public $handler;

    /**
     * @param callable(ContainerContract, RouteContract):ServiceResponseContract $handler
     */
    public function __construct(
        callable $handler,
    ) {
        $this->handler = $handler;
    }
}
