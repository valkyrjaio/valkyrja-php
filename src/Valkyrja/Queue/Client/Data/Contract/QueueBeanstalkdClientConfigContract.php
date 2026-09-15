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
    public string $beanstalkdHost {
        get;
    }

    public int $beanstalkdPort {
        get;
    }

    /** @var non-empty-string */
    public string $beanstalkdTube {
        get;
    }

    /** @var int<0, max> */
    public int $beanstalkdTimeToRelease {
        get;
    }
}
