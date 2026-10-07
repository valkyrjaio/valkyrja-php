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

interface QueueRedisClientConfigContract
{
    /** @var non-empty-string */
    public string $redisHost {
        get;
    }

    public int $redisPort {
        get;
    }

    /** @var non-empty-string */
    public string $redisQueue {
        get;
    }

    /** @var non-empty-string */
    public string $redisWorkerName {
        get;
    }
}
