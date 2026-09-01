<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Throwable;

use Throwable;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Throwable\Contract\GrpcThrowable;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcInvalidArgumentException;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcRuntimeException;
use Valkyrja\Grpc\Throwable\Exception\CancelledException;
use Valkyrja\Grpc\Throwable\Exception\GrpcConcurrentSendException;
use Valkyrja\Grpc\Throwable\Exception\GrpcNonStreamingSendException;
use Valkyrja\Grpc\Throwable\Exception\MetadataInvalidKeyException;
use Valkyrja\Grpc\Throwable\Exception\MetadataInvalidValueException;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Throwable\Contract\ValkyrjaThrowable;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaInvalidArgumentException;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaRuntimeException;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, GrpcThrowable::class);
        self::isA(ValkyrjaThrowable::class, GrpcThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(GrpcThrowable::class, GrpcInvalidArgumentException::class);
        self::isA(ValkyrjaInvalidArgumentException::class, GrpcInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(GrpcThrowable::class, GrpcRuntimeException::class);
        self::isA(ValkyrjaRuntimeException::class, GrpcRuntimeException::class);
    }

    public function testCancelledException(): void
    {
        self::isA(GrpcRuntimeException::class, CancelledException::class);
    }

    public function testMetadataInvalidKeyException(): void
    {
        self::isA(GrpcRuntimeException::class, MetadataInvalidKeyException::class);
    }

    public function testMetadataInvalidValueException(): void
    {
        self::isA(GrpcRuntimeException::class, MetadataInvalidValueException::class);
    }

    public function testNonStreamingSendException(): void
    {
        self::isA(GrpcRuntimeException::class, GrpcNonStreamingSendException::class);
    }

    public function testConcurrentSendException(): void
    {
        self::isA(GrpcRuntimeException::class, GrpcConcurrentSendException::class);
    }

    public function testCancelledExceptionDefaults(): void
    {
        $exception = new CancelledException();

        self::assertSame('', $exception->getMessage());
        self::assertNull($exception->getReason());
    }

    public function testCancelledExceptionCarriesItsReason(): void
    {
        $exception = new CancelledException('stopped', CancellationReason::DEADLINE_EXCEEDED);

        self::assertSame('stopped', $exception->getMessage());
        self::assertSame(CancellationReason::DEADLINE_EXCEEDED, $exception->getReason());
    }
}
