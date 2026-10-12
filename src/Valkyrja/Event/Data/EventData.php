<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Event\Data;

use Closure;
use Valkyrja\Event\Data\Contract\ListenerContract;

readonly class EventData
{
    /**
     * The listeners.
     *
     * @param array<class-string, array<string, string>> $events    The events
     * @param array<string, Closure():ListenerContract>  $listeners The listeners
     */
    public function __construct(
        public array $events = [],
        public array $listeners = [],
    ) {
    }
}
