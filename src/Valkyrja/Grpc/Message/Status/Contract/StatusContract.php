<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Status\Contract;

use Valkyrja\Grpc\Message\Enum\StatusCode;

/**
 * The immutable outcome of a gRPC call: a code, a human-readable message, and optional rich error
 * details.
 *
 * Mirrors the pattern HTTP uses for status code plus reason phrase, with an additional field for
 * the `google.rpc.Status` protobuf bytes carried in the `grpc-status-details-bin` trailer.
 */
interface StatusContract
{
    /**
     * Get the gRPC status code.
     */
    public function getCode(): StatusCode;

    /**
     * Get the human-readable message. Never empty; defaults from the code.
     *
     * @return non-empty-string
     */
    public function getMessage(): string;

    /**
     * Get the optional rich error details (`google.rpc.Status` protobuf bytes).
     */
    public function getDetails(): string|null;

    /**
     * Determine whether details are present.
     */
    public function hasDetails(): bool;

    /**
     * Determine whether the call succeeded.
     */
    public function isOk(): bool;

    /**
     * Determine whether the call was cancelled or its deadline elapsed.
     */
    public function isCancellation(): bool;

    /**
     * Create a new status with the specified code.
     */
    public function withCode(StatusCode $code): static;

    /**
     * Create a new status with the specified message.
     *
     * @param non-empty-string $message The message
     */
    public function withMessage(string $message): static;

    /**
     * Create a new status with the specified rich error details.
     *
     * @param string|null $details The `google.rpc.Status` protobuf bytes
     */
    public function withDetails(string|null $details): static;
}
