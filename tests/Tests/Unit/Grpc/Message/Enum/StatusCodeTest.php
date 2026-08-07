<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Enum;

use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Enum\StatusText;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function array_map;

final class StatusCodeTest extends TestCase
{
    public function testWireValues(): void
    {
        self::assertSame(0, StatusCode::OK->value);
        self::assertSame(1, StatusCode::CANCELLED->value);
        self::assertSame(2, StatusCode::UNKNOWN->value);
        self::assertSame(3, StatusCode::INVALID_ARGUMENT->value);
        self::assertSame(4, StatusCode::DEADLINE_EXCEEDED->value);
        self::assertSame(5, StatusCode::NOT_FOUND->value);
        self::assertSame(6, StatusCode::ALREADY_EXISTS->value);
        self::assertSame(7, StatusCode::PERMISSION_DENIED->value);
        self::assertSame(8, StatusCode::RESOURCE_EXHAUSTED->value);
        self::assertSame(9, StatusCode::FAILED_PRECONDITION->value);
        self::assertSame(10, StatusCode::ABORTED->value);
        self::assertSame(11, StatusCode::OUT_OF_RANGE->value);
        self::assertSame(12, StatusCode::UNIMPLEMENTED->value);
        self::assertSame(13, StatusCode::INTERNAL->value);
        self::assertSame(14, StatusCode::UNAVAILABLE->value);
        self::assertSame(15, StatusCode::DATA_LOSS->value);
        self::assertSame(16, StatusCode::UNAUTHENTICATED->value);
    }

    public function testFromWireValue(): void
    {
        self::assertSame(StatusCode::NOT_FOUND, StatusCode::from(5));
        self::assertNull(StatusCode::tryFrom(17));
    }

    public function testEveryCodeHasADefaultMessage(): void
    {
        foreach (StatusCode::cases() as $case) {
            self::assertNotSame('', $case->getDefaultMessage());
        }
    }

    public function testDefaultMessage(): void
    {
        self::assertSame('OK', StatusCode::OK->getDefaultMessage());
        self::assertSame('The operation was cancelled', StatusCode::CANCELLED->getDefaultMessage());
        self::assertSame('Unimplemented', StatusCode::UNIMPLEMENTED->getDefaultMessage());
        self::assertSame('Internal error', StatusCode::INTERNAL->getDefaultMessage());
    }

    public function testIsOk(): void
    {
        self::assertTrue(StatusCode::OK->isOk());
        self::assertFalse(StatusCode::CANCELLED->isOk());
    }

    public function testIsCancellation(): void
    {
        self::assertTrue(StatusCode::CANCELLED->isCancellation());
        self::assertTrue(StatusCode::DEADLINE_EXCEEDED->isCancellation());
        self::assertFalse(StatusCode::OK->isCancellation());
        self::assertFalse(StatusCode::INTERNAL->isCancellation());
    }

    public function testEveryCodeHasMatchingText(): void
    {
        $codes = array_map(static fn (StatusCode $case): string => $case->name, StatusCode::cases());
        $texts = array_map(static fn (StatusText $case): string => $case->name, StatusText::cases());

        self::assertSame($codes, $texts);
    }
}
