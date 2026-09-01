<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Cancellation\Contract;

use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Throwable\Exception\CancelledException;

interface CancellationTokenContract
{
    /**
     * Determine whether cancellation has fired.
     *
     * The token is mutable — an adapter fires it from the transport at any point — so a previous
     * answer says nothing about the next one.
     *
     * @phpstan-impure
     */
    public function isCancelled(): bool;

    /**
     * Get the cause of cancellation, or null if not cancelled.
     */
    public function getReason(): CancellationReason|null;

    /**
     * Throw if the call is cancelled; otherwise do nothing.
     *
     * @throws CancelledException
     */
    public function throwIfCancelled(): void;

    /**
     * Register a listener fired when cancellation occurs. If already cancelled, the listener runs
     * immediately.
     *
     * @param callable():void $listener The callback to run on cancellation
     */
    public function onCancelled(callable $listener): void;
}
