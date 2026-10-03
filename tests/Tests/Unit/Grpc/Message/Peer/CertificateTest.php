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

use Valkyrja\Grpc\Message\Peer\Certificate;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CertificateTest extends TestCase
{
    public function testEncoded(): void
    {
        self::assertSame('der-bytes', new Certificate('der-bytes')->getEncoded());
    }
}
