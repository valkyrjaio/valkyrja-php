<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Throwable\Exception\Abstract;

use Valkyrja\Grpc\Throwable\Contract\GrpcThrowable;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaInvalidArgumentException;

abstract class GrpcInvalidArgumentException extends ValkyrjaInvalidArgumentException implements GrpcThrowable
{
}
