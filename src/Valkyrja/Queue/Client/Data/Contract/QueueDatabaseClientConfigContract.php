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

interface QueueDatabaseClientConfigContract
{
    /** @var non-empty-string */
    public string $queueDatabaseClientQueue {
        get;
    }

    /** @var non-empty-string */
    public string $queueDatabaseClientTable {
        get;
    }

    /** @var int<1, max> */
    public int $queueDatabaseClientReservationTimeoutMs {
        get;
    }
}
