<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Data\Contract;

use Valkyrja\Application\Entry\Abstract\InternalQueue;

interface QueueDeferredClientConfigContract
{
    /** @var class-string<InternalQueue> */
    public string $deferredEntry {
        get;
    }
}
