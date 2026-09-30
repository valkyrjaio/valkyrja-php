<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Auth\Provider;

use Override;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Auth\Authenticator\Contract\AuthenticatorContract;
use Valkyrja\Auth\Authenticator\SessionAuthenticator;
use Valkyrja\Auth\Data\AuthConfig;
use Valkyrja\Auth\Data\AuthSessionConfig;
use Valkyrja\Auth\Data\Contract\AuthConfigContract;
use Valkyrja\Auth\Data\Contract\AuthSessionConfigContract;
use Valkyrja\Auth\Hasher\Contract\PasswordHasherContract;
use Valkyrja\Auth\Hasher\PhpPasswordHasher;
use Valkyrja\Auth\Store\Contract\StoreContract;
use Valkyrja\Auth\Store\InMemoryStore;
use Valkyrja\Auth\Store\NullStore;
use Valkyrja\Auth\Store\OrmStore;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\Session\Manager\Contract\SessionContract;

class AuthServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the auth config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof AuthConfigContract) {
            $container->setSingleton(AuthConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            AuthConfigContract::class,
            new AuthConfig()
        );
    }

    /**
     * Publish the session authenticator config service.
     */
    public static function publishSessionConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof AuthSessionConfigContract) {
            $container->setSingleton(AuthSessionConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            AuthSessionConfigContract::class,
            new AuthSessionConfig()
        );
    }

    /**
     * Publish the authenticator service.
     */
    public static function publishAuthenticator(ContainerContract $container): void
    {
        $config = $container->getSingleton(AuthConfigContract::class);

        $container->setSingleton(
            AuthenticatorContract::class,
            $container->getSingleton($config->defaultAuthenticator),
        );
    }

    /**
     * Publish the session authenticator service.
     */
    public static function publishSessionAuthenticator(ContainerContract $container): void
    {
        $config        = $container->getSingleton(AuthConfigContract::class);
        $sessionConfig = $container->getSingleton(AuthSessionConfigContract::class);

        $container->setSingleton(
            SessionAuthenticator::class,
            new SessionAuthenticator(
                session: $container->getSingleton(SessionContract::class),
                store: $container->getSingleton(StoreContract::class),
                hasher: $container->getSingleton(PasswordHasherContract::class),
                entity: $config->defaultUserEntity,
                sessionItemId: $sessionConfig->sessionItemId,
                allowedClasses: $sessionConfig->sessionAllowedClasses
            ),
        );
    }

    /**
     * Publish the store service.
     */
    public static function publishStore(ContainerContract $container): void
    {
        $config = $container->getSingleton(AuthConfigContract::class);

        $container->setSingleton(
            StoreContract::class,
            $container->getSingleton($config->defaultStore),
        );
    }

    /**
     * Publish the orm store service.
     */
    public static function publishOrmStore(ContainerContract $container): void
    {
        $container->setSingleton(
            OrmStore::class,
            new OrmStore(
                orm: $container->getSingleton(ManagerContract::class)
            ),
        );
    }

    /**
     * Publish the in memory store service.
     */
    public static function publishInMemoryStore(ContainerContract $container): void
    {
        $container->setSingleton(
            InMemoryStore::class,
            new InMemoryStore(),
        );
    }

    /**
     * Publish the null store service.
     */
    public static function publishNullStore(ContainerContract $container): void
    {
        $container->setSingleton(
            NullStore::class,
            new NullStore(),
        );
    }

    /**
     * Publish the password hasher service.
     */
    public static function publishPasswordHasher(ContainerContract $container): void
    {
        $container->setSingleton(
            PasswordHasherContract::class,
            new PhpPasswordHasher()
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            AuthConfigContract::class        => [self::class, 'publishConfig'],
            AuthSessionConfigContract::class => [self::class, 'publishSessionConfig'],
            AuthenticatorContract::class     => [self::class, 'publishAuthenticator'],
            SessionAuthenticator::class      => [self::class, 'publishSessionAuthenticator'],
            StoreContract::class             => [self::class, 'publishStore'],
            OrmStore::class                  => [self::class, 'publishOrmStore'],
            InMemoryStore::class             => [self::class, 'publishInMemoryStore'],
            NullStore::class                 => [self::class, 'publishNullStore'],
            PasswordHasherContract::class    => [self::class, 'publishPasswordHasher'],
        ];
    }
}
