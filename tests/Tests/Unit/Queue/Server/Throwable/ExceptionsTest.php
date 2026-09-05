<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Server\Throwable;

use Throwable;
use Valkyrja\Queue\Server\Throwable\Contract\QueueServerThrowable;
use Valkyrja\Queue\Server\Throwable\Exception\Abstract\QueueServerInvalidArgumentException;
use Valkyrja\Queue\Server\Throwable\Exception\Abstract\QueueServerRuntimeException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNonRetryableJobException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerWorkerShutdownException;
use Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable;
use Valkyrja\Queue\Throwable\Contract\QueueThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueInvalidArgumentException;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, QueueServerThrowable::class);
        self::isA(QueueThrowable::class, QueueServerThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(QueueServerThrowable::class, QueueServerInvalidArgumentException::class);
        self::isA(QueueInvalidArgumentException::class, QueueServerInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(QueueServerThrowable::class, QueueServerRuntimeException::class);
        self::isA(QueueRuntimeException::class, QueueServerRuntimeException::class);
    }

    public function testNonRetryableJobException(): void
    {
        self::isA(QueueServerRuntimeException::class, QueueServerNonRetryableJobException::class);
        // The retry policy middleware dead-letters on this marker, so the
        // marker is what the exception must carry, not only the name
        self::isA(QueueNonRetryableThrowable::class, QueueServerNonRetryableJobException::class);
    }

    public function testWorkerShutdownException(): void
    {
        self::isA(QueueServerRuntimeException::class, QueueServerWorkerShutdownException::class);
    }
}
