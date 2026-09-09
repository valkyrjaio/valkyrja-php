<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Support\Time;

use function microtime;

class Microtime
{
    protected static float|null $frozenTime = null;

    /**
     * Freeze the microtime.
     */
    public static function freeze(float $microtime): void
    {
        static::$frozenTime = $microtime;
    }

    /**
     * Unfreeze the microtime.
     */
    public static function unfreeze(): void
    {
        static::$frozenTime = null;
    }

    /**
     * Get the frozen, or unfrozen microtime.
     */
    public static function get(): float
    {
        return static::$frozenTime ?? static::microtime();
    }

    /**
     * Get the frozen, or unfrozen microtime, in epoch milliseconds.
     *
     * A caller that stamps a wire field wants a whole number of milliseconds,
     * not a float of seconds, so the conversion lives here rather than in each
     * caller. The floor keeps the value unsigned: a frozen time before the
     * epoch would otherwise give a negative stamp.
     *
     * @return int<0, max>
     */
    public static function now(): int
    {
        $now = (int) (static::get() * 1000.0);

        return $now > 0
            ? $now
            : 0;
    }

    /**
     * Get the microtime.
     */
    protected static function microtime(): float
    {
        return microtime(true);
    }
}
