<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Enum;

/**
 * The cause behind a cancelled call.
 *
 * Cancellation unifies two causes: client-initiated cancellation (HTTP/2 RST_STREAM) and deadline
 * expiry. Code only checks cancellation; it consults the reason when the distinction matters.
 */
enum CancellationReason: string
{
    case CLIENT_CANCELLED  = 'client-cancelled';
    case DEADLINE_EXCEEDED = 'deadline-exceeded';
}
