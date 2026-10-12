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

interface QueueAmqpClientConfigContract
{
    /** @var non-empty-string */
    public string $queueAmqpClientHost {
        get;
    }

    public int $queueAmqpClientPort {
        get;
    }

    /** @var non-empty-string */
    public string $queueAmqpClientUser {
        get;
    }

    public string $queueAmqpClientPassword {
        get;
    }

    /** @var non-empty-string */
    public string $queueAmqpClientVhost {
        get;
    }

    /** @var non-empty-string */
    public string $queueAmqpClientQueue {
        get;
    }

    public string $queueAmqpClientExchange {
        get;
    }
}
