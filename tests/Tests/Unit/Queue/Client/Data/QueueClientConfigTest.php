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

use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueClientConfig;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueueClientConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(QueueClientConfigContract::class, new QueueClientConfig());
    }

    public function testDefaults(): void
    {
        self::assertSame(RedisClient::class, new QueueClientConfig()->defaultQueueClient);
    }

    public function testCustomValuesAreStored(): void
    {
        self::assertSame(
            SyncClient::class,
            new QueueClientConfig(defaultQueueClient: SyncClient::class)->defaultQueueClient
        );
    }
}
