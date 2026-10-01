<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Jwt\Provider;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use Override;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Jwt\Data\Contract\JwtConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtEdDsaConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtFirebaseConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtHsConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtRsConfigContract;
use Valkyrja\Jwt\Data\JwtConfig;
use Valkyrja\Jwt\Data\JwtEdDsaConfig;
use Valkyrja\Jwt\Data\JwtFirebaseConfig;
use Valkyrja\Jwt\Data\JwtHsConfig;
use Valkyrja\Jwt\Data\JwtRsConfig;
use Valkyrja\Jwt\Enum\Algorithm;
use Valkyrja\Jwt\Manager\Contract\JwtContract;
use Valkyrja\Jwt\Manager\FirebaseJwt;
use Valkyrja\Jwt\Manager\NullJwt;

class JwtServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the jwt config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof JwtConfigContract) {
            $container->setSingleton(JwtConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            JwtConfigContract::class,
            new JwtConfig()
        );
    }

    /**
     * Publish the HMAC jwt config service.
     */
    public static function publishHsConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof JwtHsConfigContract) {
            $container->setSingleton(JwtHsConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            JwtHsConfigContract::class,
            new JwtHsConfig()
        );
    }

    /**
     * Publish the RSA jwt config service.
     */
    public static function publishRsConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof JwtRsConfigContract) {
            $container->setSingleton(JwtRsConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            JwtRsConfigContract::class,
            new JwtRsConfig()
        );
    }

    /**
     * Publish the EdDSA jwt config service.
     */
    public static function publishEdDsaConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof JwtEdDsaConfigContract) {
            $container->setSingleton(JwtEdDsaConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            JwtEdDsaConfigContract::class,
            new JwtEdDsaConfig()
        );
    }

    /**
     * Publish the firebase jwt config service.
     */
    public static function publishFirebaseConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof JwtFirebaseConfigContract) {
            $container->setSingleton(JwtFirebaseConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            JwtFirebaseConfigContract::class,
            new JwtFirebaseConfig()
        );
    }

    /**
     * Publish the jwt service.
     */
    public static function publishJwt(ContainerContract $container): void
    {
        $config = $container->getSingleton(JwtConfigContract::class);

        $container->setSingleton(
            JwtContract::class,
            $container->getSingleton($config->defaultJwt),
        );
    }

    /**
     * Publish the jwt service.
     *
     * The algorithm decides which key config the container resolves. An
     * application that signs with one algorithm never constructs the key config
     * for the other algorithms.
     *
     * Each `match` arm calls `getSingleton()` itself, and the encode `match` and
     * the decode `match` repeat that call. The repetition is deliberate. A local
     * variable would have to resolve every key config before the `match` chooses
     * one. That would construct a config for an algorithm the application does
     * not sign with, and the application may never define one. Keep the call
     * inside the arm. Do not lift it into a variable.
     */
    public static function publishFirebaseJwt(ContainerContract $container): void
    {
        $appConfig = $container->getSingleton(ConfigContract::class);
        $config    = $container->getSingleton(JwtFirebaseConfigContract::class);
        $algorithm = $config->firebaseAlgorithm;

        /** @var OpenSSLAsymmetricKey|OpenSSLCertificate|string $encodeKey */
        $encodeKey = match ($algorithm) {
            Algorithm::HS256, Algorithm::HS384, Algorithm::HS512 => $container->getSingleton(JwtHsConfigContract::class)->hsKey,
            Algorithm::RS256, Algorithm::RS384, Algorithm::RS512 => $container->getSingleton(JwtRsConfigContract::class)->rsPrivateKey,
            Algorithm::EdDSA                                     => $container->getSingleton(JwtEdDsaConfigContract::class)->edDsaPrivateKey,
            default                                              => $appConfig->key,
        };

        /** @var OpenSSLAsymmetricKey|OpenSSLCertificate|string $decodeKey */
        $decodeKey = match ($algorithm) {
            Algorithm::HS256, Algorithm::HS384, Algorithm::HS512 => $container->getSingleton(JwtHsConfigContract::class)->hsKey,
            Algorithm::RS256, Algorithm::RS384, Algorithm::RS512 => $container->getSingleton(JwtRsConfigContract::class)->rsPublicKey,
            Algorithm::EdDSA                                     => $container->getSingleton(JwtEdDsaConfigContract::class)->edDsaPublicKey,
            default                                              => $appConfig->key,
        };

        $container->setSingleton(
            FirebaseJwt::class,
            new FirebaseJwt(
                encodeKey: $encodeKey,
                decodeKey: $decodeKey,
                algorithm: $algorithm,
            ),
        );
    }

    /**
     * Publish the jwt service.
     */
    public static function publishNullJwt(ContainerContract $container): void
    {
        $container->setSingleton(
            NullJwt::class,
            new NullJwt(),
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            JwtConfigContract::class         => [self::class, 'publishConfig'],
            JwtHsConfigContract::class       => [self::class, 'publishHsConfig'],
            JwtRsConfigContract::class       => [self::class, 'publishRsConfig'],
            JwtEdDsaConfigContract::class    => [self::class, 'publishEdDsaConfig'],
            JwtFirebaseConfigContract::class => [self::class, 'publishFirebaseConfig'],
            JwtContract::class               => [self::class, 'publishJwt'],
            FirebaseJwt::class               => [self::class, 'publishFirebaseJwt'],
            NullJwt::class                   => [self::class, 'publishNullJwt'],
        ];
    }
}
