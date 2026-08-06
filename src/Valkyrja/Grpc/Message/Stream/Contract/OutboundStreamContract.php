<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Stream\Contract;

use Valkyrja\Grpc\Message\Metadata\Contract\MetadataContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;

/**
 * The transport-side primitive a streaming-model (bidirectional) call writes to.
 *
 * Supplied by the worker adapter and driven by the framework's streaming dispatch: headers are
 * committed once at stream open, messages are pushed as the handler emits them, and the terminal
 * status and trailing metadata are written at close.
 *
 * All three methods are invoked from the single handler execution unit, so implementations need not
 * guard against each other.
 */
interface OutboundStreamContract
{
    /**
     * Commit the initial response headers.
     *
     * Called exactly once, at stream open (the first emit, or the close if the handler emitted
     * nothing).
     *
     * @param MetadataContract $initialMetadata The initial metadata to send as headers
     */
    public function sendHeaders(MetadataContract $initialMetadata): void;

    /**
     * Push one outbound message to the wire.
     *
     * @param mixed $message The decoded message
     */
    public function sendMessage(mixed $message): void;

    /**
     * Close the call with the terminal response's status and trailing metadata.
     *
     * @param ServiceResponseContract $terminal The terminal response — its message list is unused,
     *                                          since messages went through `sendMessage()`
     */
    public function close(ServiceResponseContract $terminal): void;
}
