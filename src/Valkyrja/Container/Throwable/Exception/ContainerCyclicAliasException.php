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
     * @param class-string $id    The id it points at, which reaches the first again
     */
    public function __construct(
        string $alias,
        string $id,
        int $code = 0,
        Throwable|null $previous = null
    ) {
        $message = "Alias `$alias` cannot point at `$id`, because `$id` already reaches `$alias`.";

        parent::__construct($message, $code, $previous);
    }
}
