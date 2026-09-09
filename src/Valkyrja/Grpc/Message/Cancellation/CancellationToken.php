<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Cancellation;

use Override;
use Valkyrja\Grpc\Message\Cancellation\Contract\CancellationTokenContract;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Throwable\Exception\CancelledException;

class CancellationToken implements CancellationTokenContract
{
    /** @var array<array-key, callable():void> */
    protected array $listeners = [];

    protected bool $cancelled = false;

    protected CancellationReason|null $reason = null;

    /**
     * Create a token that never fires — the sentinel for a call with no cancellation source.
     */
    public static function never(): self
    {
        return new self();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getReason(): CancellationReason|null
    {
        return $this->reason;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function throwIfCancelled(): void
    {
        if ($this->cancelled) {
            throw new CancelledException(
                message: 'The call has been cancelled',
                reason: $this->reason
            );
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function onCancelled(callable $listener): void
    {
        if ($this->cancelled) {
            $listener();

            return;
        }

        $this->listeners[] = $listener;
    }

    /**
     * Fire cancellation with the given reason.
     *
     * Idempotent: subsequent calls are ignored so the first cause wins and listeners run at most
     * once.
     *
     * @param CancellationReason $reason The cause of cancellation
     */
    public function cancel(CancellationReason $reason): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->reason    = $reason;
        $this->cancelled = true;

        $listeners       = $this->listeners;
        $this->listeners = [];

        foreach ($listeners as $listener) {
            $listener();
        }
    }
}
