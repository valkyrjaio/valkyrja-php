<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Client\Data;

use Valkyrja\Application\Data\HttpConfig;
use Valkyrja\Application\Entry\Abstract\InternalQueue;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSyncClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Provider\QueueClientComponentProvider;
use Valkyrja\Tests\Fixtures\Application\Entry\InternalQueueFixture;

/**
 * An HTTP application that pushes its jobs through the sync client.
 */
final class SyncHostConfigFixture extends HttpConfig implements QueueClientConfigContract, QueueSyncClientConfigContract
{
    /** @var class-string<ClientContract> */
    public string $defaultQueueClient = SyncClient::class;

    /** @var class-string<InternalQueue> */
    public string $syncEntry = InternalQueueFixture::class;

    public function __construct()
    {
        parent::__construct(
            applicationName: 'host',
            providers: [new QueueClientComponentProvider()],
        );
    }
}
