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

interface QueueBeanstalkdClientConfigContract
{
    /** @var non-empty-string */
    public string $queueBeanstalkdClientHost {
        get;
    }

    public int $queueBeanstalkdClientPort {
        get;
    }

    /** @var non-empty-string */
    public string $queueBeanstalkdClientTube {
        get;
    }

    /** @var int<0, max> */
    public int $queueBeanstalkdClientTimeToRelease {
        get;
    }
}
