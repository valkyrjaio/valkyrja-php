<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Call;

use RuntimeException;
use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Deadline\Deadline;
use Valkyrja\Grpc\Message\Enum\AddressType;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Metadata\Metadata;
use Valkyrja\Grpc\Message\Peer\Peer;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Throwable\Exception\GrpcConcurrentSendException;
use Valkyrja\Grpc\Throwable\Exception\GrpcNonStreamingSendException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function count;
use function iterator_to_array;

final class ServiceCallTest extends TestCase
{
    public function testDefaults(): void
    {
        $call = new ServiceCall('/pkg.Service/Method');

        self::assertSame('/pkg.Service/Method', $call->getMethod());
        self::assertSame([], $call->getMessages());
        self::assertSame([], $call->getMetadata()->toArray());
        self::assertFalse($call->getDeadline()->hasDeadline());
        self::assertFalse($call->getCancellation()->isCancelled());
        self::assertSame('unknown', $call->getPeer()->getAddress());
        self::assertNull($call->getRoute());
        self::assertFalse($call->hasRoute());
        self::assertFalse($call->isStreaming());
    }

    public function testExplicitValues(): void
    {
        $metadata     = new Metadata(['x' => ['one']]);
        $deadline     = Deadline::fromTimeout(5.0);
        $cancellation = new CancellationToken();
        $peer         = new Peer('10.0.0.1:80', AddressType::IPV4);
        $route        = self::createStub(RouteContract::class);

        $call = new ServiceCall(
            method: '/pkg.Service/Method',
            messages: ['one'],
            metadata: $metadata,
            deadline: $deadline,
            cancellation: $cancellation,
            peer: $peer,
            route: $route,
        );

        self::assertSame($metadata, $call->getMetadata());
        self::assertSame($deadline, $call->getDeadline());
        self::assertSame($cancellation, $call->getCancellation());
        self::assertSame($peer, $call->getPeer());
        self::assertSame(['one'], $call->getMessages());
        self::assertSame($route, $call->getRoute());
        self::assertTrue($call->hasRoute());
    }

    public function testUnary(): void
    {
        $call = ServiceCall::unary('/pkg.Service/Method', 'payload');

        self::assertSame('/pkg.Service/Method', $call->getMethod());
        self::assertSame(['payload'], $call->getMessages());
    }

    public function testWithRoute(): void
    {
        $route = self::createStub(RouteContract::class);
        $call  = new ServiceCall('/pkg.Service/Method');
        $new   = $call->withRoute($route);

        self::assertNotSame($call, $new);
        self::assertFalse($call->hasRoute());
        self::assertTrue($new->hasRoute());
        self::assertSame($route, $new->getRoute());
    }

    public function testSendOnABufferedCallThrows(): void
    {
        $this->expectException(GrpcNonStreamingSendException::class);

        new ServiceCall('/pkg.Service/Method')->send('payload');
    }

    public function testSendOnAStreamingCall(): void
    {
        $sent = [];

        $call = new ServiceCall(
            method: '/pkg.Service/Method',
            sink: static function (mixed $message) use (&$sent): void {
                $sent[] = $message;
            },
        );

        self::assertTrue($call->isStreaming());

        $call->send('one');
        $call->send('two');

        self::assertSame(['one', 'two'], $sent);
    }

    public function testAReentrantSendThrows(): void
    {
        $call = null;

        $call = new ServiceCall(
            method: '/pkg.Service/Method',
            sink: static function () use (&$call): void {
                /** @var ServiceCall $call */
                $call->send('nested');
            },
        );

        $this->expectException(GrpcConcurrentSendException::class);

        $call->send('outer');
    }

    public function testTheSendGuardIsReleasedAfterAThrow(): void
    {
        $call = new ServiceCall(
            method: '/pkg.Service/Method',
            sink: static function (mixed $message): void {
                if ($message === 'boom') {
                    throw new RuntimeException('boom');
                }
            },
        );

        try {
            $call->send('boom');
        } catch (RuntimeException) {
            // The guard must be released even when the sink throws.
        }

        $call->send('fine');

        self::assertTrue($call->isStreaming());
    }

    public function testCancellableYieldsEverythingWhenUncancelled(): void
    {
        $call = new ServiceCall('/pkg.Service/Method');

        self::assertSame(['one', 'two'], iterator_to_array($call->cancellable(['one', 'two']), false));
    }

    public function testCancellableYieldsNothingForAnEmptyUncancelledSource(): void
    {
        $call = new ServiceCall('/pkg.Service/Method');

        self::assertSame([], iterator_to_array($call->cancellable([])));
    }

    public function testCancellableYieldsNothingWhenAlreadyCancelled(): void
    {
        $cancellation = new CancellationToken();
        $cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $call = new ServiceCall('/pkg.Service/Method', cancellation: $cancellation);

        $pulled = 0;

        $source = (static function () use (&$pulled): iterable {
            foreach (['one', 'two'] as $item) {
                $pulled++;

                yield $item;
            }
        })();

        self::assertSame([], iterator_to_array($call->cancellable($source), false));
        self::assertSame(0, $pulled);
    }

    public function testCancellableStopsAtTheNextItemOnceCancelled(): void
    {
        $cancellation = new CancellationToken();

        $call = new ServiceCall('/pkg.Service/Method', cancellation: $cancellation);

        $yielded = [];

        foreach ($call->cancellable(['one', 'two', 'three']) as $item) {
            $yielded[] = $item;

            $cancellation->cancel(CancellationReason::CLIENT_CANCELLED);
        }

        self::assertSame(['one'], $yielded);
    }

    public function testCancellableOnlyAdvancesTheSourceAsTheConsumerPulls(): void
    {
        $produced = 0;

        $source = (static function () use (&$produced): iterable {
            while (true) {
                $produced++;

                yield "message $produced";
            }
        })();

        $call    = new ServiceCall('/pkg.Service/Method');
        $drained = [];

        foreach ($call->cancellable($source) as $message) {
            $drained[] = $message;

            // A consumer that stops reading — what an adapter does when the transport cannot
            // accept another message and it pauses the drain until the peer catches up.
            if (count($drained) === 2) {
                break;
            }
        }

        self::assertSame(['message 1', 'message 2'], $drained);
        // The producer never ran ahead of the consumer. Outbound flow control is the adapter's to
        // apply, and this laziness is what lets it: a peer that stops reading stops the handler
        // rather than letting it buffer without bound.
        self::assertSame(2, $produced);
    }

    public function testCancellablePreservesKeys(): void
    {
        $call = new ServiceCall('/pkg.Service/Method');

        self::assertSame(['a' => 1, 'b' => 2], iterator_to_array($call->cancellable(['a' => 1, 'b' => 2])));
    }
}
