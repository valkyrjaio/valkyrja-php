<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Stream;

use Generator;
use IteratorAggregate;
use Override;

use function array_shift;

/**
 * @implements IteratorAggregate<array-key, mixed>
 */
class InboundMessageStream implements IteratorAggregate
{
    /** @var list<mixed> */
    protected array $messages = [];

    protected bool $completed = false;

    /** @var (callable():void)|null */
    protected $awaitNext;

    /** @var (callable():void)|null */
    protected $onConsumed;

    /**
     * @param (callable():void)|null $onConsumed Run once each time the handler consumes a message —
     *                                           the adapter wires this to request one more message
     *                                           from the transport, keeping the buffer at its
     *                                           high-water mark
     * @param (callable():void)|null $awaitNext  Suspend until the transport can feed more messages;
     *                                           null when the runtime cannot suspend, in which case
     *                                           a dry buffer ends the iteration
     */
    public function __construct(
        callable|null $onConsumed = null,
        callable|null $awaitNext = null,
    ) {
        $this->onConsumed = $onConsumed;
        $this->awaitNext  = $awaitNext;
    }

    /**
     * Feed one decoded message into the stream. Called from the transport side as messages arrive.
     *
     * @param mixed $message The decoded message
     */
    public function offer(mixed $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Signal that no more messages will arrive — the client half-closed, or the call was cancelled.
     *
     * A drained iteration then ends rather than awaiting more.
     */
    public function complete(): void
    {
        $this->completed = true;
    }

    /**
     * Determine whether the stream has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * @inheritDoc
     *
     * @return Generator<int, mixed>
     */
    #[Override]
    public function getIterator(): Generator
    {
        return $this->drain();
    }

    /**
     * Yield each message as it becomes available, ending when no more will arrive.
     *
     * The draining is its own generator rather than the body of `getIterator()` so that the
     * contract method stays a plain accessor returning the stream's cursor.
     *
     * @return Generator<int, mixed>
     */
    protected function drain(): Generator
    {
        while ($this->awaitMessage()) {
            /** @psalm-suppress MixedAssignment Message payloads are deliberately agnostic */
            $message = array_shift($this->messages);

            yield $message;

            $this->messageConsumed();
        }
    }

    /**
     * Wait until a message is available, or report that the stream has ended.
     *
     * Kept out of the generator body so the iteration itself stays a plain "while there is a
     * message" loop: the waiting is what is open-ended, not the yielding.
     *
     * @return bool True when a message is waiting; false when no more will arrive
     */
    protected function awaitMessage(): bool
    {
        $awaitNext = $this->awaitNext;

        while ($this->messages === []) {
            if ($this->completed || $awaitNext === null) {
                return false;
            }

            $awaitNext();
        }

        return true;
    }

    /**
     * Tell the adapter one message was drained, so it can request the next from the transport.
     */
    protected function messageConsumed(): void
    {
        $onConsumed = $this->onConsumed;

        if ($onConsumed !== null) {
            $onConsumed();
        }
    }
}
