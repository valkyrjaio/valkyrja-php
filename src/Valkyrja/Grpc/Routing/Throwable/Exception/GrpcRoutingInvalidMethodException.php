<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Throwable\Exception;

use Valkyrja\Grpc\Routing\Throwable\Exception\Abstract\GrpcRoutingRuntimeException;

/**
 * Thrown when a fully-qualified gRPC method is malformed, or when the service map holds no route
 * for it.
 */
class GrpcRoutingInvalidMethodException extends GrpcRoutingRuntimeException
{
}
