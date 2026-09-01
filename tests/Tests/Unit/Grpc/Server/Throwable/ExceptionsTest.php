<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Server\Throwable;

use Throwable;
use Valkyrja\Grpc\Server\Throwable\Contract\GrpcServerThrowable;
use Valkyrja\Grpc\Server\Throwable\Exception\Abstract\GrpcServerInvalidArgumentException;
use Valkyrja\Grpc\Server\Throwable\Exception\Abstract\GrpcServerRuntimeException;
use Valkyrja\Grpc\Throwable\Contract\GrpcThrowable;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcInvalidArgumentException;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, GrpcServerThrowable::class);
        self::isA(GrpcThrowable::class, GrpcServerThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(GrpcServerThrowable::class, GrpcServerInvalidArgumentException::class);
        self::isA(GrpcInvalidArgumentException::class, GrpcServerInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(GrpcServerThrowable::class, GrpcServerRuntimeException::class);
        self::isA(GrpcRuntimeException::class, GrpcServerRuntimeException::class);
    }
}
