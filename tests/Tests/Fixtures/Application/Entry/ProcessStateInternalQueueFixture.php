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
use Valkyrja\Application\Entry\Abstract\InternalQueue;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;

/**
 * Runs each job in a debug queue application whose base path and timezone differ from the host's.
 */
final class ProcessStateInternalQueueFixture extends InternalQueue
{
    /** @var non-empty-string */
    public const string TIMEZONE = 'Asia/Tokyo';

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getConfig(): QueueConfigContract
    {
        return new QueueConfig(
            dir: __DIR__,
            debugMode: true,
            timezone: self::TIMEZONE,
            providers: [new QueueTestComponentProviderFixture()],
        );
    }
}
