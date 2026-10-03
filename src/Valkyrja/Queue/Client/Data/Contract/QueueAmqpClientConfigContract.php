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
    public string $amqpHost {
        get;
    }

    public int $amqpPort {
        get;
    }

    /** @var non-empty-string */
    public string $amqpUser {
        get;
    }

    public string $amqpPassword {
        get;
    }

    /** @var non-empty-string */
    public string $amqpVhost {
        get;
    }

    /** @var non-empty-string */
    public string $amqpQueue {
        get;
    }

    public string $amqpExchange {
        get;
    }
}
