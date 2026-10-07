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

use function gmdate;
use function intdiv;
use function sprintf;

class Rfc3339
{
    /**
     * Render epoch milliseconds as a UTC instant with millisecond precision.
     *
     * @param int<0, max> $milliseconds The epoch milliseconds
     *
     * @return non-empty-string
     */
    public static function fromMilliseconds(int $milliseconds): string
    {
        /** @var non-empty-string */
        return gmdate('Y-m-d\TH:i:s', intdiv($milliseconds, 1000))
            . sprintf('.%03dZ', $milliseconds % 1000);
    }
}
