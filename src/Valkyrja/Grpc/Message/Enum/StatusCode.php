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
 * The canonical gRPC status codes.
 *
 * These are gRPC-specific and intentionally distinct from HTTP status codes: the ranges, names, and
 * semantics differ, and reusing HTTP's would accept values with no meaning on the wire. The backing
 * value is the integer carried in the `grpc-status` trailer (0–16).
 *
 * @see https://grpc.github.io/grpc/core/md_doc_statuscodes.html
 */
enum StatusCode: int
{
    case OK                  = 0;
    case CANCELLED           = 1;
    case UNKNOWN             = 2;
    case INVALID_ARGUMENT    = 3;
    case DEADLINE_EXCEEDED   = 4;
    case NOT_FOUND           = 5;
    case ALREADY_EXISTS      = 6;
    case PERMISSION_DENIED   = 7;
    case RESOURCE_EXHAUSTED  = 8;
    case FAILED_PRECONDITION = 9;
    case ABORTED             = 10;
    case OUT_OF_RANGE        = 11;
    case UNIMPLEMENTED       = 12;
    case INTERNAL            = 13;
    case UNAVAILABLE         = 14;
    case DATA_LOSS           = 15;
    case UNAUTHENTICATED     = 16;

    /**
     * Get the default human-readable message for this code.
     *
     * @return non-empty-string
     */
    public function getDefaultMessage(): string
    {
        return match ($this) {
            self::OK                  => 'OK',
            self::CANCELLED           => 'The operation was cancelled',
            self::UNKNOWN             => 'Unknown error',
            self::INVALID_ARGUMENT    => 'Invalid argument',
            self::DEADLINE_EXCEEDED   => 'Deadline exceeded',
            self::NOT_FOUND           => 'Not found',
            self::ALREADY_EXISTS      => 'Already exists',
            self::PERMISSION_DENIED   => 'Permission denied',
            self::RESOURCE_EXHAUSTED  => 'Resource exhausted',
            self::FAILED_PRECONDITION => 'Failed precondition',
            self::ABORTED             => 'Aborted',
            self::OUT_OF_RANGE        => 'Out of range',
            self::UNIMPLEMENTED       => 'Unimplemented',
            self::INTERNAL            => 'Internal error',
            self::UNAVAILABLE         => 'Unavailable',
            self::DATA_LOSS           => 'Data loss',
            self::UNAUTHENTICATED     => 'Unauthenticated',
        };
    }

    /**
     * Whether this code represents a successful call outcome.
     */
    public function isOk(): bool
    {
        return $this === self::OK;
    }

    /**
     * Whether this code represents a cancellation outcome.
     */
    public function isCancellation(): bool
    {
        return $this === self::CANCELLED
            || $this === self::DEADLINE_EXCEEDED;
    }
}
