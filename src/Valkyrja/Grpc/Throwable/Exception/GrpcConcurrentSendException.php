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
 * Thrown when a streaming call's sink is re-entered while a send is already in flight.
 *
 * Sends are serialized and the transport is not re-entrant, so a nested or concurrent send is
 * rejected fast rather than silently corrupting the stream's framing.
 */
class GrpcConcurrentSendException extends GrpcRuntimeException
{
}
