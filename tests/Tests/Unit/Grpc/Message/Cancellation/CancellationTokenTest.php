<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Cancellation;

use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Throwable\Exception\CancelledException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CancellationTokenTest extends TestCase
{
    public function testStartsUncancelled(): void
    {
        $token = new CancellationToken();

        self::assertFalse($token->isCancelled());
        self::assertNull($token->getReason());
    }

    public function testNeverIsUncancelled(): void
    {
        self::assertFalse(CancellationToken::never()->isCancelled());
    }

    public function testThrowIfCancelledDoesNothingWhenUncancelled(): void
    {
        new CancellationToken()->throwIfCancelled();

        self::assertTrue(true);
    }

    public function testCancel(): void
    {
        $token = new CancellationToken();

        $token->cancel(CancellationReason::DEADLINE_EXCEEDED);

        self::assertTrue($token->isCancelled());
        self::assertSame(CancellationReason::DEADLINE_EXCEEDED, $token->getReason());
    }

    public function testTheFirstCauseWins(): void
    {
        $token = new CancellationToken();

        $token->cancel(CancellationReason::CLIENT_CANCELLED);
        $token->cancel(CancellationReason::DEADLINE_EXCEEDED);

        self::assertSame(CancellationReason::CLIENT_CANCELLED, $token->getReason());
    }

    public function testThrowIfCancelledCarriesTheReason(): void
    {
        $token = new CancellationToken();

        $token->cancel(CancellationReason::DEADLINE_EXCEEDED);

        try {
            $token->throwIfCancelled();

            self::fail('Expected a cancellation exception');
        } catch (CancelledException $exception) {
            self::assertSame(CancellationReason::DEADLINE_EXCEEDED, $exception->getReason());
            self::assertSame('The call has been cancelled', $exception->getMessage());
        }
    }

    public function testListenersFireOnCancel(): void
    {
        $token = new CancellationToken();
        $fired = 0;

        $token->onCancelled(static function () use (&$fired): void {
            $fired++;
        });

        self::assertSame(0, $fired);

        $token->cancel(CancellationReason::CLIENT_CANCELLED);

        self::assertSame(1, $fired);
    }

    public function testListenersFireAtMostOnce(): void
    {
        $token = new CancellationToken();
        $fired = 0;

        $token->onCancelled(static function () use (&$fired): void {
            $fired++;
        });

        $token->cancel(CancellationReason::CLIENT_CANCELLED);
        $token->cancel(CancellationReason::CLIENT_CANCELLED);

        self::assertSame(1, $fired);
    }

    public function testAListenerRegisteredAfterCancellationRunsImmediately(): void
    {
        $token = new CancellationToken();

        $token->cancel(CancellationReason::CLIENT_CANCELLED);

        $fired = 0;

        $token->onCancelled(static function () use (&$fired): void {
            $fired++;
        });

        self::assertSame(1, $fired);
    }
}
