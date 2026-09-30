<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Session\Provider;

use Override;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Cache\Manager\Contract\CacheContract;
use Valkyrja\Cli\Interaction\Input\Contract\InputContract;
use Valkyrja\Cli\Routing\Constant\OptionName;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Crypt\Manager\Contract\CryptContract;
use Valkyrja\Http\Message\Constant\HeaderName;
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Jwt\Manager\Contract\JwtContract;
use Valkyrja\Session\Data\Contract\SessionConfigContract;
use Valkyrja\Session\Data\Contract\SessionJwtConfigContract;
use Valkyrja\Session\Data\Contract\SessionLogConfigContract;
use Valkyrja\Session\Data\Contract\SessionPhpConfigContract;
use Valkyrja\Session\Data\Contract\SessionTokenConfigContract;
use Valkyrja\Session\Data\SessionConfig;
use Valkyrja\Session\Data\SessionJwtConfig;
use Valkyrja\Session\Data\SessionLogConfig;
use Valkyrja\Session\Data\SessionPhpConfig;
use Valkyrja\Session\Data\SessionTokenConfig;
use Valkyrja\Session\Manager\CacheSession;
use Valkyrja\Session\Manager\Contract\SessionContract;
use Valkyrja\Session\Manager\Cookie\CookieSession;
use Valkyrja\Session\Manager\Cookie\EncryptedCookieSession;
use Valkyrja\Session\Manager\Jwt\Cli\EncryptedOptionJwtSession;
use Valkyrja\Session\Manager\Jwt\Cli\OptionJwtSession;
use Valkyrja\Session\Manager\Jwt\Http\EncryptedHeaderJwtSession;
use Valkyrja\Session\Manager\Jwt\Http\HeaderJwtSession;
use Valkyrja\Session\Manager\LogSession;
use Valkyrja\Session\Manager\NullSession;
use Valkyrja\Session\Manager\PhpSession;
use Valkyrja\Session\Manager\Token\Cli\EncryptedOptionTokenSession;
use Valkyrja\Session\Manager\Token\Cli\OptionTokenSession;
use Valkyrja\Session\Manager\Token\Http\EncryptedHeaderTokenSession;
use Valkyrja\Session\Manager\Token\Http\HeaderTokenSession;

class SessionServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the session config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof SessionConfigContract) {
            $container->setSingleton(SessionConfigContract::class, $config);

            return;
        }

        $container->setSingleton(SessionConfigContract::class, new SessionConfig());
    }

    /**
     * Publish the cookie session config service.
     */
    public static function publishPhpConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof SessionPhpConfigContract) {
            $container->setSingleton(SessionPhpConfigContract::class, $config);

            return;
        }

        $container->setSingleton(SessionPhpConfigContract::class, new SessionPhpConfig());
    }

    /**
     * Publish the jwt session config service.
     */
    public static function publishJwtConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof SessionJwtConfigContract) {
            $container->setSingleton(SessionJwtConfigContract::class, $config);

            return;
        }

        $container->setSingleton(SessionJwtConfigContract::class, new SessionJwtConfig());
    }

    /**
     * Publish the token session config service.
     */
    public static function publishTokenConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof SessionTokenConfigContract) {
            $container->setSingleton(SessionTokenConfigContract::class, $config);

            return;
        }

        $container->setSingleton(SessionTokenConfigContract::class, new SessionTokenConfig());
    }

    /**
     * Publish the log session config service.
     */
    public static function publishLogConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof SessionLogConfigContract) {
            $container->setSingleton(SessionLogConfigContract::class, $config);

            return;
        }

        $container->setSingleton(SessionLogConfigContract::class, new SessionLogConfig());
    }

    /**
     * Publish the session service.
     */
    public static function publishSession(ContainerContract $container): void
    {
        $config = $container->getSingleton(SessionConfigContract::class);

        $container->setSingleton(
            SessionContract::class,
            $container->getSingleton($config->defaultSession),
        );
    }

    /**
     * Publish the php session service.
     */
    public static function publishPhpSession(ContainerContract $container): void
    {
        $config = $container->getSingleton(SessionConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;

        $container->setSingleton(
            PhpSession::class,
            new PhpSession(
                cookieConfig: $container->getSingleton(SessionPhpConfigContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
            ),
        );
    }

    /**
     * Publish the null session service.
     */
    public static function publishNullSession(ContainerContract $container): void
    {
        $config = $container->getSingleton(SessionConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;

        $container->setSingleton(
            NullSession::class,
            new NullSession(
                sessionId: $sessionId,
                sessionName: $sessionName,
            ),
        );
    }

    /**
     * Publish the cache session service.
     */
    public static function publishCacheSession(ContainerContract $container): void
    {
        $config = $container->getSingleton(SessionConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;

        $container->setSingleton(
            CacheSession::class,
            new CacheSession(
                cache: $container->getSingleton(CacheContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
            ),
        );
    }

    /**
     * Publish the cookie session service.
     */
    public static function publishCookieSession(ContainerContract $container): void
    {
        $config = $container->getSingleton(SessionConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;

        $container->setSingleton(
            CookieSession::class,
            new CookieSession(
                request: $container->getSingleton(ServerRequestContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
            ),
        );
    }

    /**
     * Publish the encrypted cookie session service.
     */
    public static function publishEncryptedCookieSession(ContainerContract $container): void
    {
        $config = $container->getSingleton(SessionConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;

        $container->setSingleton(
            EncryptedCookieSession::class,
            new EncryptedCookieSession(
                crypt: $container->getSingleton(CryptContract::class),
                request: $container->getSingleton(ServerRequestContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
            ),
        );
    }

    /**
     * Publish the option jwt session service.
     */
    public static function publishOptionJwtSession(ContainerContract $container): void
    {
        $config    = $container->getSingleton(SessionConfigContract::class);
        $jwtConfig = $container->getSingleton(SessionJwtConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;
        $optionName  = $jwtConfig->jwtOptionName;

        $container->setSingleton(
            OptionJwtSession::class,
            new OptionJwtSession(
                jwt: $container->getSingleton(JwtContract::class),
                input: $container->getSingleton(InputContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
                optionName: $optionName ?? OptionName::TOKEN,
            ),
        );
    }

    /**
     * Publish the encrypted option jwt session service.
     */
    public static function publishEncryptedOptionJwtSession(ContainerContract $container): void
    {
        $config    = $container->getSingleton(SessionConfigContract::class);
        $jwtConfig = $container->getSingleton(SessionJwtConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;
        $optionName  = $jwtConfig->jwtOptionName;

        $container->setSingleton(
            EncryptedOptionJwtSession::class,
            new EncryptedOptionJwtSession(
                crypt: $container->getSingleton(CryptContract::class),
                jwt: $container->getSingleton(JwtContract::class),
                input: $container->getSingleton(InputContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
                optionName: $optionName ?? OptionName::TOKEN,
            ),
        );
    }

    /**
     * Publish the header jwt session service.
     */
    public static function publishHeaderJwtSession(ContainerContract $container): void
    {
        $config    = $container->getSingleton(SessionConfigContract::class);
        $jwtConfig = $container->getSingleton(SessionJwtConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;
        $headerName  = $jwtConfig->jwtHeaderName;

        $container->setSingleton(
            HeaderJwtSession::class,
            new HeaderJwtSession(
                jwt: $container->getSingleton(JwtContract::class),
                request: $container->getSingleton(ServerRequestContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
                headerName: $headerName ?? HeaderName::AUTHORIZATION,
            ),
        );
    }

    /**
     * Publish the encrypted header jwt session service.
     */
    public static function publishEncryptedHeaderJwtSession(ContainerContract $container): void
    {
        $config    = $container->getSingleton(SessionConfigContract::class);
        $jwtConfig = $container->getSingleton(SessionJwtConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;
        $headerName  = $jwtConfig->jwtHeaderName;

        $container->setSingleton(
            EncryptedHeaderJwtSession::class,
            new EncryptedHeaderJwtSession(
                crypt: $container->getSingleton(CryptContract::class),
                jwt: $container->getSingleton(JwtContract::class),
                request: $container->getSingleton(ServerRequestContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
                headerName: $headerName ?? HeaderName::AUTHORIZATION,
            ),
        );
    }

    /**
     * Publish the option token session service.
     */
    public static function publishOptionTokenSession(ContainerContract $container): void
    {
        $config      = $container->getSingleton(SessionConfigContract::class);
        $tokenConfig = $container->getSingleton(SessionTokenConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;
        $optionName  = $tokenConfig->tokenOptionName;

        $container->setSingleton(
            OptionTokenSession::class,
            new OptionTokenSession(
                input: $container->getSingleton(InputContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
                optionName: $optionName ?? OptionName::TOKEN,
            ),
        );
    }

    /**
     * Publish the encrypted option token session service.
     */
    public static function publishEncryptedOptionTokenSession(ContainerContract $container): void
    {
        $config      = $container->getSingleton(SessionConfigContract::class);
        $tokenConfig = $container->getSingleton(SessionTokenConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;
        $optionName  = $tokenConfig->tokenOptionName;

        $container->setSingleton(
            EncryptedOptionTokenSession::class,
            new EncryptedOptionTokenSession(
                crypt: $container->getSingleton(CryptContract::class),
                input: $container->getSingleton(InputContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
                optionName: $optionName ?? OptionName::TOKEN,
            ),
        );
    }

    /**
     * Publish the header token session service.
     */
    public static function publishHeaderTokenSession(ContainerContract $container): void
    {
        $config      = $container->getSingleton(SessionConfigContract::class);
        $tokenConfig = $container->getSingleton(SessionTokenConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;
        $headerName  = $tokenConfig->tokenHeaderName;

        $container->setSingleton(
            HeaderTokenSession::class,
            new HeaderTokenSession(
                request: $container->getSingleton(ServerRequestContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
                headerName: $headerName ?? HeaderName::AUTHORIZATION,
            ),
        );
    }

    /**
     * Publish the encrypted header token session service.
     */
    public static function publishEncryptedHeaderTokenSession(ContainerContract $container): void
    {
        $config      = $container->getSingleton(SessionConfigContract::class);
        $tokenConfig = $container->getSingleton(SessionTokenConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;
        $headerName  = $tokenConfig->tokenHeaderName;

        $container->setSingleton(
            EncryptedHeaderTokenSession::class,
            new EncryptedHeaderTokenSession(
                crypt: $container->getSingleton(CryptContract::class),
                request: $container->getSingleton(ServerRequestContract::class),
                sessionId: $sessionId,
                sessionName: $sessionName,
                headerName: $headerName ?? HeaderName::AUTHORIZATION,
            ),
        );
    }

    /**
     * Publish the log session service.
     */
    public static function publishLogSession(ContainerContract $container): void
    {
        $config    = $container->getSingleton(SessionConfigContract::class);
        $logConfig = $container->getSingleton(SessionLogConfigContract::class);

        $sessionId   = $config->sessionId;
        $sessionName = $config->sessionName;

        $container->setSingleton(
            LogSession::class,
            new LogSession(
                logger: $container->getSingleton($logConfig->sessionLogLogger),
                sessionId: $sessionId,
                sessionName: $sessionName,
            ),
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            SessionConfigContract::class       => [self::class, 'publishConfig'],
            SessionPhpConfigContract::class    => [self::class, 'publishPhpConfig'],
            SessionJwtConfigContract::class    => [self::class, 'publishJwtConfig'],
            SessionTokenConfigContract::class  => [self::class, 'publishTokenConfig'],
            SessionLogConfigContract::class    => [self::class, 'publishLogConfig'],
            SessionContract::class             => [self::class, 'publishSession'],
            PhpSession::class                  => [self::class, 'publishPhpSession'],
            NullSession::class                 => [self::class, 'publishNullSession'],
            CacheSession::class                => [self::class, 'publishCacheSession'],
            CookieSession::class               => [self::class, 'publishCookieSession'],
            EncryptedCookieSession::class      => [self::class, 'publishEncryptedCookieSession'],
            OptionJwtSession::class            => [self::class, 'publishOptionJwtSession'],
            EncryptedOptionJwtSession::class   => [self::class, 'publishEncryptedOptionJwtSession'],
            HeaderJwtSession::class            => [self::class, 'publishHeaderJwtSession'],
            EncryptedHeaderJwtSession::class   => [self::class, 'publishEncryptedHeaderJwtSession'],
            OptionTokenSession::class          => [self::class, 'publishOptionTokenSession'],
            EncryptedOptionTokenSession::class => [self::class, 'publishEncryptedOptionTokenSession'],
            HeaderTokenSession::class          => [self::class, 'publishHeaderTokenSession'],
            EncryptedHeaderTokenSession::class => [self::class, 'publishEncryptedHeaderTokenSession'],
            LogSession::class                  => [self::class, 'publishLogSession'],
        ];
    }
}
