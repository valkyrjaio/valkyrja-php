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

use Valkyrja\Queue\Client\Data\Contract\QueuePubSubClientConfigContract;
use Valkyrja\Queue\Client\Data\QueuePubSubClientConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueuePubSubClientConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(QueuePubSubClientConfigContract::class, new QueuePubSubClientConfig());
    }

    public function testDefaults(): void
    {
        $config = new QueuePubSubClientConfig();

        self::assertSame('valkyrja', $config->pubSubProjectId);
        self::assertSame('default', $config->pubSubTopic);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new QueuePubSubClientConfig(
            pubSubProjectId: 'valkyrja-tests',
            pubSubTopic: 'jobs',
        );

        self::assertSame('valkyrja-tests', $config->pubSubProjectId);
        self::assertSame('jobs', $config->pubSubTopic);
    }
}
