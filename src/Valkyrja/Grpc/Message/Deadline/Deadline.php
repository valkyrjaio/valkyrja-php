<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Deadline;

use Override;
use Valkyrja\Grpc\Message\Deadline\Contract\DeadlineContract;
use Valkyrja\Support\Time\Microtime;

class Deadline implements DeadlineContract
{
    /**
     * The sentinel "remaining budget" reported when no deadline is set. A large but finite duration
     * (100 years, in seconds) so it reads as effectively infinite without overflowing in downstream
     * arithmetic — a consistent choice every language port can reproduce.
     */
    public const float INFINITE_REMAINING = 365.0 * 100.0 * 86400.0;

    protected float $absoluteTime;

    /**
     * @param float|null $absoluteTime The absolute expiry time as a unix timestamp in seconds, or
     *                                 null for the no-deadline sentinel
     * @param bool       $hasDeadline  Whether a deadline is set at all
     */
    public function __construct(
        float|null $absoluteTime = null,
        protected bool $hasDeadline = false,
    ) {
        // The sentinel is finite, so arithmetic on an absent deadline's absolute time cannot
        // overflow the way PHP_FLOAT_MAX does.
        $this->absoluteTime = $absoluteTime ?? Microtime::get() + self::INFINITE_REMAINING;
    }

    /**
     * Create a deadline from a relative timeout, in seconds.
     */
    public static function fromTimeout(float $timeout): static
    {
        return new static(absoluteTime: Microtime::get() + $timeout, hasDeadline: true);
    }

    /**
     * Create a deadline from an absolute unix timestamp, in seconds.
     */
    public static function fromAbsolute(float $absoluteTime): static
    {
        return new static(absoluteTime: $absoluteTime, hasDeadline: true);
    }

    /**
     * Create the sentinel deadline for a call with no client-set deadline.
     */
    public static function none(): static
    {
        return new static();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAbsoluteTime(): float
    {
        return $this->absoluteTime;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRemaining(): float
    {
        if (! $this->hasDeadline) {
            return self::INFINITE_REMAINING;
        }

        $remaining = $this->absoluteTime - Microtime::get();

        return $remaining < 0.0
            ? 0.0
            : $remaining;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isExpired(): bool
    {
        return $this->hasDeadline
            && Microtime::get() >= $this->absoluteTime;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasDeadline(): bool
    {
        return $this->hasDeadline;
    }
}
