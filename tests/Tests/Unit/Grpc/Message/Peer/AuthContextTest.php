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

use Valkyrja\Grpc\Message\Peer\AuthContext;
use Valkyrja\Grpc\Message\Peer\Certificate;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class AuthContextTest extends TestCase
{
    public function testDefaults(): void
    {
        $authContext = new AuthContext();

        self::assertSame('insecure', $authContext->getType());
        self::assertSame([], $authContext->getProperties());
        self::assertSame([], $authContext->getPeerCertificates());
        self::assertNull($authContext->getPeerSubject());
        self::assertNull($authContext->getTransportSecurityType());
    }

    public function testWithEverything(): void
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

    public function testInsecure(): void
    {
        self::assertSame(AuthContext::TYPE_INSECURE, AuthContext::insecure()->getType());
    }
}
