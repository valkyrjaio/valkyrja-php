<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Event\Attribute\Listener;

use Valkyrja\Event\Attribute\Listener\Handler;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the listener handler attribute.
 */
final class HandlerTest extends TestCase
{
    public function testHandler(): void
    {
        $handler = static fn () => null;

        $listenerHandler = new Handler($handler);

        self::assertSame($handler, $listenerHandler->handler);
    }
}
