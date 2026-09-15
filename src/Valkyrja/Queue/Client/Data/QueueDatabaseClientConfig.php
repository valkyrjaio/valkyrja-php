<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Data;

use Valkyrja\Queue\Client\Data\Contract\QueueDatabaseClientConfigContract;
use Valkyrja\Queue\Client\Manager\DatabaseClient;

class QueueDatabaseClientConfig implements QueueDatabaseClientConfigContract
{
    /**
     * @param non-empty-string $databaseQueue The queue that jobs are written under
     * @param non-empty-string $databaseTable The table that jobs are written to
     */
    public function __construct(
        public readonly string $databaseQueue = 'default',
        public readonly string $databaseTable = DatabaseClient::DEFAULT_TABLE,
    ) {
    }
}
