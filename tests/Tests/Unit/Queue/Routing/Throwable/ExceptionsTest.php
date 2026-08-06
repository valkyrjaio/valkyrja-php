<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Routing\Throwable;

use Throwable;
use Valkyrja\Queue\Routing\Throwable\Contract\QueueRoutingThrowable;
use Valkyrja\Queue\Routing\Throwable\Exception\Abstract\QueueRoutingInvalidArgumentException;
use Valkyrja\Queue\Routing\Throwable\Exception\Abstract\QueueRoutingRuntimeException;
use Valkyrja\Queue\Routing\Throwable\Exception\QueueRoutingInvalidRouteNameException;
use Valkyrja\Queue\Throwable\Contract\QueueThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueInvalidArgumentException;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, QueueRoutingThrowable::class);
        self::isA(QueueThrowable::class, QueueRoutingThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(QueueRoutingThrowable::class, QueueRoutingInvalidArgumentException::class);
        self::isA(QueueInvalidArgumentException::class, QueueRoutingInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(QueueRoutingThrowable::class, QueueRoutingRuntimeException::class);
        self::isA(QueueRuntimeException::class, QueueRoutingRuntimeException::class);
    }

    public function testInvalidRouteNameException(): void
    {
        self::isA(QueueRoutingInvalidArgumentException::class, QueueRoutingInvalidRouteNameException::class);
    }
}
