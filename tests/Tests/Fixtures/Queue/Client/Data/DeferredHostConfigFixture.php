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
use Valkyrja\Queue\Client\Data\Contract\QueueDeferredClientConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DeferredClient;
use Valkyrja\Queue\Client\Provider\QueueClientComponentProvider;
use Valkyrja\Tests\Fixtures\Application\Entry\InternalQueueFixture;

/**
 * An HTTP application that pushes its jobs through the deferred client.
 */
final class DeferredHostConfigFixture extends HttpConfig implements QueueClientConfigContract, QueueDeferredClientConfigContract
{
    /** @var class-string<ClientContract> */
    public string $defaultQueueClient = DeferredClient::class;

    /** @var class-string<InternalQueue> */
    public string $deferredEntry = InternalQueueFixture::class;

    public function __construct()
    {
        parent::__construct(
            applicationName: 'host',
            providers: [new QueueClientComponentProvider()],
        );
    }
}
