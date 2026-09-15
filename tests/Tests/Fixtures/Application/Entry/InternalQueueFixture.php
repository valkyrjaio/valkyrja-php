<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Entry;

use Override;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\Abstract\InternalQueue;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;

/**
 * Runs each job against the fixture routes, and counts how often its config is read.
 */
final class InternalQueueFixture extends InternalQueue
{
    public static int $configCount = 0;

    public static function reset(): void
    {
        self::$configCount = 0;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getConfig(): QueueConfigContract
    {
        self::$configCount++;

        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
