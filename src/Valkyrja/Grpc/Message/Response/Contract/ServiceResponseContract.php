<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Response\Contract;

use Valkyrja\Grpc\Message\Metadata\Contract\MetadataContract;
use Valkyrja\Grpc\Message\Status\Contract\StatusContract;

interface ServiceResponseContract
{
    /**
     * Get the call outcome.
     */
    public function getStatus(): StatusContract;

    /**
     * Create a new response with the specified status.
     */
    public function withStatus(StatusContract $status): static;

    /**
     * Get the initial response metadata (leading HTTP/2 headers).
     */
    public function getInitialMetadata(): MetadataContract;

    /**
     * Create a new response with the specified initial metadata.
     */
    public function withInitialMetadata(MetadataContract $metadata): static;

    /**
     * Get the trailing response metadata (HTTP/2 trailing headers).
     */
    public function getTrailingMetadata(): MetadataContract;

    /**
     * Create a new response with the specified trailing metadata.
     */
    public function withTrailingMetadata(MetadataContract $metadata): static;

    /**
     * Get the outbound messages.
     *
     * @return iterable<array-key, mixed>
     */
    public function getMessages(): iterable;

    /**
     * Create a new response with the specified outbound messages.
     *
     * @param iterable<array-key, mixed> $messages The messages
     */
    public function withMessages(iterable $messages): static;

    /**
     * Determine whether the status is a cancellation outcome.
     */
    public function isCancellation(): bool;
}
