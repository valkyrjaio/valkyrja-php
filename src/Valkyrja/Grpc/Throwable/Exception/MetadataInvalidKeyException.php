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
 * Thrown when metadata is added under a key that is not a valid gRPC header name.
 *
 * Raised at the point of insertion — as HTTP does for invalid header names — so a malformed key
 * fails fast in the handler rather than surfacing as an opaque transport error when the response is
 * written.
 */
class MetadataInvalidKeyException extends GrpcRuntimeException
{
}
