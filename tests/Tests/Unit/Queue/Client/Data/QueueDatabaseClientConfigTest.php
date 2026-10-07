<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Data;

use Valkyrja\Queue\Client\Data\Contract\QueueDatabaseClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueDatabaseClientConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueueDatabaseClientConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(QueueDatabaseClientConfigContract::class, new QueueDatabaseClientConfig());
    }

    public function testDefaults(): void
    {
        $config = new QueueDatabaseClientConfig();

        self::assertSame('default', $config->databaseQueue);
        self::assertSame('queue_jobs', $config->databaseTable);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueueDatabaseClientConfig(
            databaseQueue: 'jobs',
            databaseTable: 'jobs_test',
        );

        self::assertSame('jobs', $config->databaseQueue);
        self::assertSame('jobs_test', $config->databaseTable);
    }
}
