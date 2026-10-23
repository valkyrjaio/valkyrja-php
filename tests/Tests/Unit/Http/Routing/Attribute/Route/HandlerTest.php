<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Http\Routing\Attribute\Route;

use Valkyrja\Http\Routing\Attribute\Route\Handler;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the route handler attribute.
 */
final class HandlerTest extends TestCase
{
    public function testAttribute(): void
    {
        $value = static fn () => null;

        $attribute = new Handler($value);

        self::assertSame($value, $attribute->handler);
    }
}
