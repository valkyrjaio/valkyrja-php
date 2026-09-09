<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Throwable;

use Throwable;
use Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable;
use Valkyrja\Queue\Throwable\Contract\QueueThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueInvalidArgumentException;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Throwable\Contract\ValkyrjaThrowable;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaInvalidArgumentException;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaRuntimeException;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, QueueThrowable::class);
        self::isA(ValkyrjaThrowable::class, QueueThrowable::class);
    }

    public function testNonRetryableThrowable(): void
    {
        self::isA(QueueThrowable::class, QueueNonRetryableThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(QueueThrowable::class, QueueInvalidArgumentException::class);
        self::isA(ValkyrjaInvalidArgumentException::class, QueueInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(QueueThrowable::class, QueueRuntimeException::class);
        self::isA(ValkyrjaRuntimeException::class, QueueRuntimeException::class);
    }
}
