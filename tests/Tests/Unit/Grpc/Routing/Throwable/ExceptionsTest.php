<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Throwable;

use Throwable;
use Valkyrja\Grpc\Routing\Throwable\Contract\GrpcRoutingThrowable;
use Valkyrja\Grpc\Routing\Throwable\Exception\Abstract\GrpcRoutingInvalidArgumentException;
use Valkyrja\Grpc\Routing\Throwable\Exception\Abstract\GrpcRoutingRuntimeException;
use Valkyrja\Grpc\Throwable\Contract\GrpcThrowable;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcInvalidArgumentException;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, GrpcRoutingThrowable::class);
        self::isA(GrpcThrowable::class, GrpcRoutingThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(GrpcRoutingThrowable::class, GrpcRoutingInvalidArgumentException::class);
        self::isA(GrpcInvalidArgumentException::class, GrpcRoutingInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(GrpcRoutingThrowable::class, GrpcRoutingRuntimeException::class);
        self::isA(GrpcRuntimeException::class, GrpcRoutingRuntimeException::class);
    }
}
