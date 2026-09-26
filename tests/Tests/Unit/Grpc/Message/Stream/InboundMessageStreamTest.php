<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Stream;

use Valkyrja\Grpc\Message\Stream\InboundMessageStream;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function array_shift;
use function iterator_to_array;

final class InboundMessageStreamTest extends TestCase
{
    public function testAnEmptyStreamEndsImmediately(): void
    {
        self::assertSame([], iterator_to_array(new InboundMessageStream()));
    }

    public function testDrainsBufferedMessages(): void
    {
        $stream = new InboundMessageStream();

        $stream->offer('one');
        $stream->offer('two');
        $stream->complete();

        self::assertSame(['one', 'two'], iterator_to_array($stream));
    }

    public function testADryBufferEndsIterationWithNoWayToSuspend(): void
    {
        $stream = new InboundMessageStream();

        $stream->offer('one');

        // Never completed, and no awaitNext callback, so iteration ends once the buffer runs dry.
        self::assertSame(['one'], iterator_to_array($stream));
    }

    public function testIsCompleted(): void
    {
        $stream = new InboundMessageStream();

        self::assertFalse($stream->isCompleted());

        $stream->complete();

        self::assertTrue($stream->isCompleted());
    }

    public function testOnConsumedFiresPerMessage(): void
    {
        $consumed = 0;

        $stream = new InboundMessageStream(
            onConsumed: static function () use (&$consumed): void {
                $consumed++;
            }
        );

        $stream->offer('one');
        $stream->offer('two');
        $stream->complete();

        iterator_to_array($stream);

        self::assertSame(2, $consumed);
    }

    public function testAwaitNextFeedsTheStreamAsTheHandlerDrainsIt(): void
    {
        $remaining = ['one', 'two'];
        $stream    = null;

        $stream = new InboundMessageStream(
            awaitNext: static function () use (&$stream, &$remaining): void {
                /** @var InboundMessageStream $stream */
                $next = array_shift($remaining);

                if ($next === null) {
                    $stream->complete();

                    return;
                }

                $stream->offer($next);
            }
        );

        self::assertSame(['one', 'two'], iterator_to_array($stream));
    }
}
