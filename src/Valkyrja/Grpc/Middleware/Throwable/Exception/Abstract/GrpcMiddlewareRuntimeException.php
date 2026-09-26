<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Middleware\Throwable\Exception\Abstract;

use Valkyrja\Grpc\Middleware\Throwable\Contract\GrpcMiddlewareThrowable;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcRuntimeException;

abstract class GrpcMiddlewareRuntimeException extends GrpcRuntimeException implements GrpcMiddlewareThrowable
{
}
