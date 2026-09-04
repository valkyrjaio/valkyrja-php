<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Container\Throwable\Exception;

use Valkyrja\Container\Throwable\Exception\ContainerCyclicAliasException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CyclicAliasExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $alias = self::class;
        $id    = ContainerCyclicAliasException::class;

        $exception = new ContainerCyclicAliasException($alias, $id);

        self::assertSame(
            "Alias `$alias` cannot point at `$id`, because `$id` already resolves to `$alias`.",
            $exception->getMessage()
        );
    }
}
