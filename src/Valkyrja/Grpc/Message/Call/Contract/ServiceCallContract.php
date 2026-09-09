<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Call\Contract;

use Valkyrja\Grpc\Message\Cancellation\Contract\CancellationTokenContract;
use Valkyrja\Grpc\Message\Deadline\Contract\DeadlineContract;
use Valkyrja\Grpc\Message\Metadata\Contract\MetadataContract;
use Valkyrja\Grpc\Message\Peer\Contract\PeerContract;
use Valkyrja\Grpc\Throwable\Exception\GrpcConcurrentSendException;
use Valkyrja\Grpc\Throwable\Exception\GrpcNonStreamingSendException;

interface ServiceCallContract
{
    /**
     * Get the fully-qualified method, `/package.Service/Method` — the service-map key.
     *
     * @return non-empty-string
     */
    public function getMethod(): string;

    /**
     * Get the inbound metadata (request headers).
     */
    public function getMetadata(): MetadataContract;

    /**
     * Get the call deadline. Never null; may be `Deadline::none()`.
     */
    public function getDeadline(): DeadlineContract;

    /**
     * Get the cancellation token. Never null; may be `CancellationToken::never()`.
     */
    public function getCancellation(): CancellationTokenContract;

    /**
     * Get the connection peer. Never null; auth may be `insecure`.
     */
    public function getPeer(): PeerContract;

    /**
     * Get the decoded inbound messages.
     *
     * Under the buffered model this is the fixed list captured before dispatch; under the streaming
     * model it is a live stream whose iteration waits for each message to arrive and ends when the
     * client half-closes.
     *
     * Under the streaming model the stream also ends on cancellation — half-close and cancel both
     * terminate iteration identically. A handler that needs to tell an orderly end from a cancelled
     * one inspects `getCancellation()` after the loop.
     *
     * @return iterable<array-key, mixed>
     */
    public function getMessages(): iterable;

    /**
     * Determine whether this call was dispatched under the streaming model (a bidirectional
     * method).
     *
     * When true, `getMessages()` is a live inbound stream and `send()` pushes outbound messages
     * while the handler runs; when false (the buffered model) the handler instead returns a single
     * ServiceResponse carrying its messages.
     */
    public function isStreaming(): bool;

    /**
     * Push one outbound message to the client from within the handler (streaming model only).
     *
     * Sends are serialized; the framework fires SendingResponse middleware once, on the first send
     * (stream open). Not for buffered calls — those return their messages on the ServiceResponse
     * instead.
     *
     * The transport is not re-entrant, so a send issued while another is in flight is rejected fast
     * rather than silently corrupting the stream.
     *
     * @param mixed $message The outbound message
     *
     * @throws GrpcNonStreamingSendException If this call is not streaming
     * @throws GrpcConcurrentSendException   If a re-entrant send is detected
     */
    public function send(mixed $message): void;

    /**
     * Wrap a source iterable so iteration checks cancellation between items, exiting iteration
     * early (yielding no further items) once the call is cancelled.
     *
     * A cooperation helper for user handlers and the outbound drain: it stops yielding rather than
     * throwing, so a cancelled stream ends cleanly. Handlers that want to fail loudly instead can
     * call `getCancellation()->throwIfCancelled()`.
     *
     * @param iterable<array-key, mixed> $source The source iterable
     *
     * @return iterable<array-key, mixed>
     */
    public function cancellable(iterable $source): iterable;
}
