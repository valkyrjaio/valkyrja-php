<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Middleware\Throwable;

use Throwable;
use Valkyrja\Queue\Middleware\Throwable\Contract\QueueMiddlewareThrowable;
use Valkyrja\Queue\Middleware\Throwable\Exception\Abstract\QueueMiddlewareInvalidArgumentException;
use Valkyrja\Queue\Middleware\Throwable\Exception\Abstract\QueueMiddlewareRuntimeException;
use Valkyrja\Queue\Throwable\Contract\QueueThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueInvalidArgumentException;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, QueueMiddlewareThrowable::class);
        self::isA(QueueThrowable::class, QueueMiddlewareThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(QueueMiddlewareThrowable::class, QueueMiddlewareInvalidArgumentException::class);
        self::isA(QueueInvalidArgumentException::class, QueueMiddlewareInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(QueueMiddlewareThrowable::class, QueueMiddlewareRuntimeException::class);
        self::isA(QueueRuntimeException::class, QueueMiddlewareRuntimeException::class);
    }
}
