<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Throwable;

use Throwable;
use Valkyrja\Queue\Client\Throwable\Contract\QueueClientThrowable;
use Valkyrja\Queue\Client\Throwable\Exception\Abstract\QueueClientInvalidArgumentException;
use Valkyrja\Queue\Client\Throwable\Exception\Abstract\QueueClientRuntimeException;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientSyncJobFailedException;
use Valkyrja\Queue\Throwable\Contract\QueueThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueInvalidArgumentException;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, QueueClientThrowable::class);
        self::isA(QueueThrowable::class, QueueClientThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(QueueClientThrowable::class, QueueClientInvalidArgumentException::class);
        self::isA(QueueInvalidArgumentException::class, QueueClientInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(QueueClientThrowable::class, QueueClientRuntimeException::class);
        self::isA(QueueRuntimeException::class, QueueClientRuntimeException::class);
    }

    public function testSyncJobFailedException(): void
    {
        self::isA(QueueClientRuntimeException::class, QueueClientSyncJobFailedException::class);
    }
}
