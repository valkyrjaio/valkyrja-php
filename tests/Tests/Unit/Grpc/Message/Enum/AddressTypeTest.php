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

use Valkyrja\Grpc\Message\Enum\AddressType;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class AddressTypeTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame('ipv4', AddressType::IPV4->value);
        self::assertSame('ipv6', AddressType::IPV6->value);
        self::assertSame('unix', AddressType::UNIX->value);
        self::assertSame('unknown', AddressType::UNKNOWN->value);
        self::assertCount(4, AddressType::cases());
    }
}
