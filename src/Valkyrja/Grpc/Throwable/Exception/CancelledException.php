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

use Throwable;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Throwable\Exception\Abstract\GrpcRuntimeException;

/**
 * Thrown when work is performed on a cancelled call.
 *
 * Raised by CancellationToken::throwIfCancelled() when a handler opts to fail loudly on
 * cancellation. It carries the CancellationReason so ThrowableCaught middleware can map it to
 * either CANCELLED or DEADLINE_EXCEEDED. Language-native cancellation exceptions are converted to
 * this type at the adapter boundary.
 */
class CancelledException extends GrpcRuntimeException
{
    public function __construct(
        string $message = '',
        protected CancellationReason|null $reason = null,
        int $code = 0,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the cause of the cancellation, if known.
     */
    public function getReason(): CancellationReason|null
    {
        return $this->reason;
    }
}
