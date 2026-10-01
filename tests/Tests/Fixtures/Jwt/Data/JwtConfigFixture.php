<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Jwt\Data;

use Valkyrja\Application\Data\Config;
use Valkyrja\Jwt\Data\Contract\JwtConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtEdDsaConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtFirebaseConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtHsConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtRsConfigContract;
use Valkyrja\Jwt\Enum\Algorithm;
use Valkyrja\Jwt\Manager\Contract\JwtContract;
use Valkyrja\Jwt\Manager\NullJwt;

/**
 * An application config that implements every jwt contract at once.
 */
final class JwtConfigFixture extends Config implements JwtConfigContract, JwtHsConfigContract, JwtRsConfigContract, JwtEdDsaConfigContract, JwtFirebaseConfigContract
{
    /**
     * @param class-string<JwtContract> $defaultJwt
     * @param non-empty-string          $hsKey
     * @param non-empty-string          $rsPrivateKey
     * @param non-empty-string          $rsPublicKey
     * @param non-empty-string          $edDsaPrivateKey
     * @param non-empty-string          $edDsaPublicKey
     */
    public function __construct(
        public string $defaultJwt = NullJwt::class,
        public Algorithm $firebaseAlgorithm = Algorithm::HS256,
        public string $hsKey = 'test-key',
        public string $rsPrivateKey = 'test-rs-private',
        public string $rsPublicKey = 'test-rs-public',
        public string $edDsaPrivateKey = 'test-eddsa-private',
        public string $edDsaPublicKey = 'test-eddsa-public',
    ) {
        parent::__construct();
    }
}
