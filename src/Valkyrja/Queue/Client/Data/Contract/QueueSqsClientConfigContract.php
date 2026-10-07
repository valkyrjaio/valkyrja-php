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

interface QueueSqsClientConfigContract
{
    /** @var non-empty-string */
    public string $sqsRegion {
        get;
    }

    /** @var non-empty-string|null */
    public string|null $sqsEndpoint {
        get;
    }

    /** @var non-empty-string|null */
    public string|null $sqsAccessKeyId {
        get;
    }

    /** @var non-empty-string|null */
    public string|null $sqsAccessKeySecret {
        get;
    }

    /** @var non-empty-string */
    public string $sqsQueueUrl {
        get;
    }

    /**
     * The long-poll wait, in seconds; 0 polls without blocking.
     *
     * This is also how long a worker can overrun `maxSeconds`, because the loop
     * reads its bounds only between receives.
     *
     * @var int<1, 20>
     */
    public int $sqsWaitTimeSeconds {
        get;
    }
}
