<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Deadline\Contract;

interface DeadlineContract
{
    /**
     * Get the absolute time at which the budget expires.
     */
    public function getAbsoluteTime(): float;

    /**
     * Get the remaining budget in seconds from now.
     *
     * Zero if already expired, and a very large duration when no deadline is set.
     */
    public function getRemaining(): float;

    /**
     * Determine whether the deadline has elapsed. Always false when no deadline is set.
     */
    public function isExpired(): bool;

    /**
     * Determine whether a deadline is set at all.
     */
    public function hasDeadline(): bool;
}
