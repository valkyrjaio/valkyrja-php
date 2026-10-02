<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Container\Throwable\Exception;

use Throwable;
use Valkyrja\Container\Throwable\Exception\Abstract\ContainerInvalidArgumentException;

class ContainerCyclicAliasException extends ContainerInvalidArgumentException
{
    /**
     * @param class-string $alias The id the chain leaves from
     * @param class-string $id    The id it points at, from which the chain returns. The
     *                            message names the pair instead when it is the alias
     */
    public function __construct(
        string $alias,
        string $id,
        int $code = 0,
        Throwable|null $previous = null
    ) {
        $message = $alias === $id
            ? "Alias `$alias` cannot point at itself."
            : "Alias `$alias` cannot reach `$id`, because the chain from `$id` returns to `$alias`.";

        parent::__construct($message, $code, $previous);
    }
}
