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
 * Thrown when an attributed gRPC handler does not return a service response.
 *
 * The handler signature is a runtime contract discovered by the attribute scan rather than one the
 * compiler can hold a controller to, so it is checked at the point the handler is invoked.
 */
class GrpcRoutingInvalidHandlerException extends GrpcRoutingRuntimeException
{
}
