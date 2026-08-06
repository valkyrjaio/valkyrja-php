<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Throwable\Exception;

use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcRuntimeException;

/**
 * Thrown when a message is pushed to a call that was not dispatched under the streaming model.
 *
 * A buffered call returns its messages on the ServiceResponse instead of pushing them through the
 * call's sink.
 */
class GrpcNonStreamingSendException extends GrpcRuntimeException
{
}
