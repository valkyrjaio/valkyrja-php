<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Entry;

use Override;
use Valkyrja\Application\Entry\PushQueue;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;

/**
 * Drives the push entry without writing headers.
 */
final class PushQueueFixture extends PushQueue
{
    public static ResponseContract|null $sent = null;

    /**
     * Reset the recorded response.
     */
    public static function reset(): void
    {
        self::$sent = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function send(ResponseContract $response): void
    {
        self::$sent = $response;
    }
}
