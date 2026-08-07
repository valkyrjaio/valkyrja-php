<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Call;

use Override;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Cancellation\Contract\CancellationTokenContract;
use Valkyrja\Grpc\Message\Deadline\Contract\DeadlineContract;
use Valkyrja\Grpc\Message\Deadline\Deadline;
use Valkyrja\Grpc\Message\Metadata\Contract\MetadataContract;
use Valkyrja\Grpc\Message\Metadata\Metadata;
use Valkyrja\Grpc\Message\Peer\Contract\PeerContract;
use Valkyrja\Grpc\Message\Peer\Peer;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Throwable\Exception\GrpcConcurrentSendException;
use Valkyrja\Grpc\Throwable\Exception\GrpcNonStreamingSendException;

class ServiceCall implements ServiceCallContract
{
    /**
     * The outbound push sink for a streaming-model call; null for a buffered call.
     *
     * @var (callable(mixed):void)|null
     */
    protected $sink;

    /** Guards against a re-entrant send — the transport sink is not re-entrant. */
    protected bool $sending = false;

    /**
     * @param non-empty-string            $method   The fully-qualified method
     * @param iterable<array-key, mixed>  $messages The inbound messages
     * @param (callable(mixed):void)|null $sink     The outbound push sink, for a streaming call
     */
    public function __construct(
        protected string $method,
        protected iterable $messages = [],
        protected MetadataContract $metadata = new Metadata(),
        protected DeadlineContract $deadline = new Deadline(),
        protected CancellationTokenContract $cancellation = new CancellationToken(),
        protected PeerContract $peer = new Peer('unknown'),
        protected RouteContract|null $route = null,
        callable|null $sink = null,
    ) {
        $this->sink = $sink;
    }

    /**
     * Create a unary call carrying a single inbound message.
     *
     * @param non-empty-string $method  The fully-qualified method
     * @param mixed            $message The single inbound message
     */
    public static function unary(string $method, mixed $message): self
    {
        return new self(method: $method, messages: [$message]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMetadata(): MetadataContract
    {
        return $this->metadata;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getDeadline(): DeadlineContract
    {
        return $this->deadline;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCancellation(): CancellationTokenContract
    {
        return $this->cancellation;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPeer(): PeerContract
    {
        return $this->peer;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMessages(): iterable
    {
        return $this->messages;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isStreaming(): bool
    {
        return $this->sink !== null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function send(mixed $message): void
    {
        $sink = $this->sink;

        if ($sink === null) {
            throw new GrpcNonStreamingSendException(
                'send() is only available on a streaming (bidirectional) call; a buffered call returns its messages on the ServiceResponse instead.'
            );
        }

        // The transport sink is not re-entrant, so a nested send corrupts the frame in flight.
        if ($this->sending) {
            throw new GrpcConcurrentSendException(
                'Concurrent send() on a streaming call: a streaming handler must emit one message at a time — sends are serialized and the transport is not re-entrant.'
            );
        }

        $this->sending = true;

        try {
            $sink($message);
        } finally {
            $this->sending = false;
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRoute(): RouteContract|null
    {
        return $this->route;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasRoute(): bool
    {
        return $this->route !== null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withRoute(RouteContract $route): static
    {
        $new = clone $this;

        $new->route = $route;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function cancellable(iterable $source): iterable
    {
        // Stop yielding rather than throw, so a cancelled call closes normally instead of raising
        // through the transport listener. The check runs before the first pull and after each yield.
        if ($this->cancellation->isCancelled()) {
            return;
        }

        /** @psalm-suppress MixedAssignment Message payloads are deliberately agnostic */
        foreach ($source as $key => $value) {
            yield $key => $value;

            if ($this->cancellation->isCancelled()) {
                return;
            }
        }
    }
}
