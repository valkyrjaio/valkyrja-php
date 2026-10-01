<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Peer;

use Valkyrja\Grpc\Message\Enum\AddressType;
use Valkyrja\Grpc\Message\Peer\AuthContext;
use Valkyrja\Grpc\Message\Peer\Peer;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class PeerTest extends TestCase
{
    public function testDefaults(): void
    {
        $peer = new Peer('127.0.0.1:1234');

        self::assertSame('127.0.0.1:1234', $peer->getAddress());
        self::assertSame(AddressType::UNKNOWN, $peer->getAddressType());
        self::assertSame(AuthContext::TYPE_INSECURE, $peer->getAuthContext()->getType());
    }

    public function testExplicitValues(): void
    {
        $authContext = new AuthContext(type: 'tls');
        $peer        = new Peer('[::1]:1234', AddressType::IPV6, $authContext);

        self::assertSame('[::1]:1234', $peer->getAddress());
        self::assertSame(AddressType::IPV6, $peer->getAddressType());
        self::assertSame($authContext, $peer->getAuthContext());
    }

    public function testInsecure(): void
    {
        $peer = Peer::insecure('unix:/var/run/sock');

        self::assertSame('unix:/var/run/sock', $peer->getAddress());
        self::assertSame(AddressType::UNKNOWN, $peer->getAddressType());
        self::assertSame(AuthContext::TYPE_INSECURE, $peer->getAuthContext()->getType());
    }
}
