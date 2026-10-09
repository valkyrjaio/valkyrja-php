<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Type\Constant;

use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Type\Constant\CastArgument;

/**
 * Test the cast argument constant.
 */
final class CastArgumentTest extends TestCase
{
    public function testValue(): void
    {
        self::assertSame('value', CastArgument::VALUE);
    }
}
