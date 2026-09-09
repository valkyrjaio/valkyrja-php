<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait;

/**
 * Counts middleware invocations so a test can assert exactly which links of a chain ran.
 */
trait MiddlewareCounterTrait
{
    protected static int $counter = 0;

    /**
     * Get the counter.
     */
    public static function getCounter(): int
    {
        return static::$counter;
    }

    /**
     * Reset the counter.
     */
    public static function resetCounter(): void
    {
        static::$counter = 0;
    }

    /**
     * Get and reset the counter.
     */
    public static function getAndResetCounter(): int
    {
        $counter = static::getCounter();

        static::resetCounter();

        return $counter;
    }

    /**
     * Update the internal counter.
     */
    protected function updateCounter(): void
    {
        static::$counter++;
    }
}
