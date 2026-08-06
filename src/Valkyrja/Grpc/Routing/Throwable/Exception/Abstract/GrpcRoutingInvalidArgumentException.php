<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Throwable\Exception\Abstract;

use Valkyrja\Grpc\Routing\Throwable\Contract\GrpcRoutingThrowable;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcInvalidArgumentException;

abstract class GrpcRoutingInvalidArgumentException extends GrpcInvalidArgumentException implements GrpcRoutingThrowable
{
}
