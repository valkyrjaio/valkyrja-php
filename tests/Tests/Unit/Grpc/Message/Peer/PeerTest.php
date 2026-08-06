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
use Valkyrja\Grpc\Message\Peer\Certificate;
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

    public function testAuthContextDefaults(): void
    {
        $authContext = new AuthContext();

        self::assertSame('insecure', $authContext->getType());
        self::assertSame([], $authContext->getProperties());
        self::assertSame([], $authContext->getPeerCertificates());
        self::assertNull($authContext->getPeerSubject());
        self::assertNull($authContext->getTransportSecurityType());
    }

    public function testAuthContextWithEverything(): void
    {
        $certificate = new Certificate('der-bytes');

        $authContext = new AuthContext(
            type: 'ssl',
            properties: ['cn' => ['example.test']],
            peerCertificates: [$certificate],
            peerSubject: 'CN=example.test',
            transportSecurityType: 'TLS_AES_256_GCM_SHA384',
        );

        self::assertSame('ssl', $authContext->getType());
        self::assertSame(['cn' => ['example.test']], $authContext->getProperties());
        self::assertSame([$certificate], $authContext->getPeerCertificates());
        self::assertSame('CN=example.test', $authContext->getPeerSubject());
        self::assertSame('TLS_AES_256_GCM_SHA384', $authContext->getTransportSecurityType());
    }

    public function testAuthContextInsecure(): void
    {
        self::assertSame(AuthContext::TYPE_INSECURE, AuthContext::insecure()->getType());
    }

    public function testCertificate(): void
    {
        self::assertSame('der-bytes', new Certificate('der-bytes')->getEncoded());
    }
}
