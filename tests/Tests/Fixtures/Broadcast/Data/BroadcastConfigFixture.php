<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Broadcast\Data;

use Valkyrja\Application\Data\Config;
use Valkyrja\Broadcast\Broadcaster\Contract\BroadcasterContract;
use Valkyrja\Broadcast\Broadcaster\NullBroadcaster;
use Valkyrja\Broadcast\Data\Contract\BroadcastConfigContract;
use Valkyrja\Broadcast\Data\Contract\BroadcastLogConfigContract;
use Valkyrja\Broadcast\Data\Contract\BroadcastPusherConfigContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;

/**
 * An application config that implements every broadcast contract at once.
 */
final class BroadcastConfigFixture extends Config implements BroadcastConfigContract, BroadcastPusherConfigContract, BroadcastLogConfigContract
{
    /**
     * @param class-string<BroadcasterContract> $defaultBroadcaster
     * @param non-empty-string                  $pusherKey
     * @param non-empty-string                  $pusherSecret
     * @param non-empty-string                  $pusherId
     * @param non-empty-string                  $pusherCluster
     * @param class-string<LoggerContract>      $broadcastLogLogger
     */
    public function __construct(
        public string $defaultBroadcaster = NullBroadcaster::class,
        public string $pusherKey = 'test-key',
        public string $pusherSecret = 'test-secret',
        public string $pusherId = 'test-id',
        public string $pusherCluster = 'eu',
        public bool $pusherUseTls = false,
        public string $broadcastLogLogger = LoggerContract::class,
    ) {
        parent::__construct();
    }
}
