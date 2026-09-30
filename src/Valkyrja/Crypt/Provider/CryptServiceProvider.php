<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Crypt\Provider;

use Override;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Crypt\Data\Contract\CryptConfigContract;
use Valkyrja\Crypt\Data\Contract\CryptSodiumConfigContract;
use Valkyrja\Crypt\Data\CryptConfig;
use Valkyrja\Crypt\Data\CryptSodiumConfig;
use Valkyrja\Crypt\Manager\Contract\CryptContract;
use Valkyrja\Crypt\Manager\NullCrypt;
use Valkyrja\Crypt\Manager\SodiumCrypt;

class CryptServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the crypt config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CryptConfigContract) {
            $container->setSingleton(CryptConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            CryptConfigContract::class,
            new CryptConfig()
        );
    }

    /**
     * Publish the sodium crypt config service.
     */
    public static function publishSodiumConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CryptSodiumConfigContract) {
            $container->setSingleton(CryptSodiumConfigContract::class, $config);

            return;
        }

        // A key has no safe default value, so the default config takes the application key.
        $container->setSingleton(
            CryptSodiumConfigContract::class,
            new CryptSodiumConfig(
                sodiumKey: $config->key
            )
        );
    }

    /**
     * Publish the crypt service.
     */
    public static function publishCrypt(ContainerContract $container): void
    {
        $config = $container->getSingleton(CryptConfigContract::class);

        $container->setSingleton(
            CryptContract::class,
            $container->getSingleton($config->defaultCrypt),
        );
    }

    /**
     * Publish the sodium crypt service.
     */
    public static function publishSodiumCrypt(ContainerContract $container): void
    {
        $config = $container->getSingleton(CryptSodiumConfigContract::class);

        $container->setSingleton(
            SodiumCrypt::class,
            new SodiumCrypt(
                key: $config->sodiumKey
            )
        );
    }

    /**
     * Publish the null crypt service.
     */
    public static function publishNullCrypt(ContainerContract $container): void
    {
        $container->setSingleton(
            NullCrypt::class,
            new NullCrypt()
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            CryptConfigContract::class       => [self::class, 'publishConfig'],
            CryptSodiumConfigContract::class => [self::class, 'publishSodiumConfig'],
            CryptContract::class             => [self::class, 'publishCrypt'],
            SodiumCrypt::class               => [self::class, 'publishSodiumCrypt'],
            NullCrypt::class                 => [self::class, 'publishNullCrypt'],
        ];
    }
}
