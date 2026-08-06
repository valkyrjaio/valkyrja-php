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
 * Thrown when metadata is added with a value whose type does not match its key's kind.
 *
 * A `-bin` key carries arbitrary bytes, every other key carries printable ASCII. Raised at the
 * point of insertion so a mismatch fails fast rather than as a transport error when the response is
 * written.
 */
class MetadataInvalidValueException extends GrpcRuntimeException
{
}
