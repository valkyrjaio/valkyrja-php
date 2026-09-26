<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Middleware\Throwable;

use Throwable;
use Valkyrja\Grpc\Middleware\Throwable\Contract\GrpcMiddlewareThrowable;
use Valkyrja\Grpc\Middleware\Throwable\Exception\Abstract\GrpcMiddlewareInvalidArgumentException;
use Valkyrja\Grpc\Middleware\Throwable\Exception\Abstract\GrpcMiddlewareRuntimeException;
use Valkyrja\Grpc\Throwable\Contract\GrpcThrowable;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcInvalidArgumentException;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, GrpcMiddlewareThrowable::class);
        self::isA(GrpcThrowable::class, GrpcMiddlewareThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(GrpcMiddlewareThrowable::class, GrpcMiddlewareInvalidArgumentException::class);
        self::isA(GrpcInvalidArgumentException::class, GrpcMiddlewareInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(GrpcMiddlewareThrowable::class, GrpcMiddlewareRuntimeException::class);
        self::isA(GrpcRuntimeException::class, GrpcMiddlewareRuntimeException::class);
    }
}
