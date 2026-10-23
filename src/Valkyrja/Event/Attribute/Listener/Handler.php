<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Event\Attribute\Listener;

use Attribute;
use Valkyrja\Attribute\Contract\ReflectionAwareAttributeContract;
use Valkyrja\Attribute\Trait\ReflectionAwareAttribute;
use Valkyrja\Container\Manager\Contract\ContainerContract;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Handler implements ReflectionAwareAttributeContract
{
    use ReflectionAwareAttribute;

    /** @var callable(ContainerContract, array<string, mixed>):mixed */
    public $handler;

    /**
     * @param callable(ContainerContract, array<string, mixed>):mixed $handler The handler
     */
    public function __construct(
        callable $handler,
    ) {
        $this->handler = $handler;
    }
}
