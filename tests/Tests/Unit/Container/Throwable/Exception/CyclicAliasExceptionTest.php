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
            "Alias `$alias` cannot reach `$id`, because the chain from `$id` returns to `$alias`.",
            $exception->getMessage()
        );
    }

    public function testMessageForAnAliasOfItself(): void
    {
        $alias = self::class;

        $exception = new ContainerCyclicAliasException($alias, $alias);

        self::assertSame("Alias `$alias` cannot point at itself.", $exception->getMessage());
    }
}
