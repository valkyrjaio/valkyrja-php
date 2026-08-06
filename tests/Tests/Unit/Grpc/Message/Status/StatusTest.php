<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Status;

use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Status\Status;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class StatusTest extends TestCase
{
    /**
     * @return iterable<string, array{callable(string|null):Status, StatusCode}>
     */
    public static function provideFactories(): iterable
    {
        yield 'cancelled' => [Status::cancelled(...), StatusCode::CANCELLED];

        yield 'unknown' => [Status::unknown(...), StatusCode::UNKNOWN];

        yield 'invalidArgument' => [Status::invalidArgument(...), StatusCode::INVALID_ARGUMENT];

        yield 'deadlineExceeded' => [Status::deadlineExceeded(...), StatusCode::DEADLINE_EXCEEDED];

        yield 'notFound' => [Status::notFound(...), StatusCode::NOT_FOUND];

        yield 'alreadyExists' => [Status::alreadyExists(...), StatusCode::ALREADY_EXISTS];

        yield 'permissionDenied' => [Status::permissionDenied(...), StatusCode::PERMISSION_DENIED];

        yield 'resourceExhausted' => [Status::resourceExhausted(...), StatusCode::RESOURCE_EXHAUSTED];

        yield 'failedPrecondition' => [Status::failedPrecondition(...), StatusCode::FAILED_PRECONDITION];

        yield 'aborted' => [Status::aborted(...), StatusCode::ABORTED];

        yield 'outOfRange' => [Status::outOfRange(...), StatusCode::OUT_OF_RANGE];

        yield 'unimplemented' => [Status::unimplemented(...), StatusCode::UNIMPLEMENTED];

        yield 'unavailable' => [Status::unavailable(...), StatusCode::UNAVAILABLE];

        yield 'dataLoss' => [Status::dataLoss(...), StatusCode::DATA_LOSS];

        yield 'unauthenticated' => [Status::unauthenticated(...), StatusCode::UNAUTHENTICATED];
    }

    public function testDefaultsToOk(): void
    {
        $status = new Status();

        self::assertSame(StatusCode::OK, $status->getCode());
        self::assertSame('OK', $status->getMessage());
        self::assertNull($status->getDetails());
        self::assertFalse($status->hasDetails());
        self::assertTrue($status->isOk());
        self::assertFalse($status->isCancellation());
    }

    public function testMessageDefaultsFromTheCode(): void
    {
        self::assertSame('Not found', new Status(StatusCode::NOT_FOUND)->getMessage());
    }

    public function testExplicitMessageWins(): void
    {
        self::assertSame('nope', new Status(StatusCode::NOT_FOUND, 'nope')->getMessage());
    }

    public function testDetails(): void
    {
        $status = new Status(StatusCode::INTERNAL, 'boom', 'details-bytes');

        self::assertSame('details-bytes', $status->getDetails());
        self::assertTrue($status->hasDetails());
    }

    public function testWithCode(): void
    {
        $status = new Status(StatusCode::OK, 'kept', 'details');
        $new    = $status->withCode(StatusCode::ABORTED);

        self::assertNotSame($status, $new);
        self::assertSame(StatusCode::OK, $status->getCode());
        self::assertSame(StatusCode::ABORTED, $new->getCode());
        self::assertSame('kept', $new->getMessage());
        self::assertSame('details', $new->getDetails());
    }

    public function testWithMessage(): void
    {
        $status = new Status(StatusCode::ABORTED);
        $new    = $status->withMessage('changed');

        self::assertNotSame($status, $new);
        self::assertSame('Aborted', $status->getMessage());
        self::assertSame('changed', $new->getMessage());
    }

    public function testWithDetails(): void
    {
        $status = new Status(StatusCode::INTERNAL);
        $new    = $status->withDetails('bytes');

        self::assertNotSame($status, $new);
        self::assertNull($status->getDetails());
        self::assertSame('bytes', $new->getDetails());
        self::assertNull($new->withDetails(null)->getDetails());
    }

    public function testIsCancellation(): void
    {
        self::assertTrue(Status::cancelled()->isCancellation());
        self::assertTrue(Status::deadlineExceeded()->isCancellation());
        self::assertFalse(Status::ok()->isCancellation());
    }

    public function testOfWithoutAMessage(): void
    {
        $status = Status::of(StatusCode::DATA_LOSS);

        self::assertSame(StatusCode::DATA_LOSS, $status->getCode());
        self::assertSame('Data loss', $status->getMessage());
    }

    public function testOfWithAMessage(): void
    {
        self::assertSame('custom', Status::of(StatusCode::DATA_LOSS, 'custom')->getMessage());
    }

    /**
     * @param callable(string|null):Status $factory
     */
    #[DataProvider('provideFactories')]
    public function testFactory(callable $factory, StatusCode $expected): void
    {
        $default = $factory(null);

        self::assertSame($expected, $default->getCode());
        self::assertSame($expected->getDefaultMessage(), $default->getMessage());
        self::assertSame('custom', $factory('custom')->getMessage());
    }

    public function testOkFactory(): void
    {
        $status = Status::ok();

        self::assertSame(StatusCode::OK, $status->getCode());
        self::assertTrue($status->isOk());
    }

    public function testInternalFactory(): void
    {
        $default = Status::internal();

        self::assertSame(StatusCode::INTERNAL, $default->getCode());
        self::assertSame('Internal error', $default->getMessage());
        self::assertNull($default->getDetails());

        $withDetails = Status::internal('boom', 'bytes');

        self::assertSame('boom', $withDetails->getMessage());
        self::assertSame('bytes', $withDetails->getDetails());
    }

    public function testForReason(): void
    {
        self::assertSame(
            StatusCode::DEADLINE_EXCEEDED,
            Status::forReason(CancellationReason::DEADLINE_EXCEEDED)->getCode()
        );
        self::assertSame(
            StatusCode::CANCELLED,
            Status::forReason(CancellationReason::CLIENT_CANCELLED)->getCode()
        );
        self::assertSame(StatusCode::CANCELLED, Status::forReason(null)->getCode());
    }
}
