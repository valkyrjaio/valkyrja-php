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
 * @see https://grpc.github.io/grpc/core/md_doc_statuscodes.html
 */
enum StatusText: string
{
    case OK                  = 'OK';
    case CANCELLED           = 'The operation was cancelled';
    case UNKNOWN             = 'Unknown error';
    case INVALID_ARGUMENT    = 'Invalid argument';
    case DEADLINE_EXCEEDED   = 'Deadline exceeded';
    case NOT_FOUND           = 'Not found';
    case ALREADY_EXISTS      = 'Already exists';
    case PERMISSION_DENIED   = 'Permission denied';
    case RESOURCE_EXHAUSTED  = 'Resource exhausted';
    case FAILED_PRECONDITION = 'Failed precondition';
    case ABORTED             = 'Aborted';
    case OUT_OF_RANGE        = 'Out of range';
    case UNIMPLEMENTED       = 'Unimplemented';
    case INTERNAL            = 'Internal error';
    case UNAVAILABLE         = 'Unavailable';
    case DATA_LOSS           = 'Data loss';
    case UNAUTHENTICATED     = 'Unauthenticated';
}
