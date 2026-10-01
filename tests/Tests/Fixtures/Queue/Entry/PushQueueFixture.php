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
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;

/**
 * Drives the push entry without writing headers.
 */
final class PushQueueFixture extends PushQueue
{
    public static ResponseContract|null $sent = null;

    public static ServerRequestContract|null $request = null;

    /**
     * Reset the recorded response and the request the entry reads.
     */
    public static function reset(): void
    {
        self::$sent    = null;
        self::$request = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getRequest(): ServerRequestContract
    {
        // The real seam reads the globals, which a test has no business setting
        return self::$request
            ?? throw new QueueServerNotConnectedException('No request was given to the push queue fixture.');
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
