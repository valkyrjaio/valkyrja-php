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

use Valkyrja\Grpc\Routing\Throwable\Exception\Abstract\GrpcRoutingRuntimeException;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingInvalidMethodException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ConcreteExceptionsTest extends TestCase
{
    public function testInvalidMethodException(): void
    {
        self::isA(GrpcRoutingRuntimeException::class, GrpcRoutingInvalidMethodException::class);
    }
}
