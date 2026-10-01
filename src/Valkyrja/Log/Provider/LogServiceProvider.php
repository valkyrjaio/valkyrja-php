<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Log\Provider;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Override;
use Psr\Log\LoggerInterface;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Log\Data\Contract\LogConfigContract;
use Valkyrja\Log\Data\Contract\LogPsrConfigContract;
use Valkyrja\Log\Data\LogConfig;
use Valkyrja\Log\Data\LogPsrConfig;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;
use Valkyrja\Log\Logger\PsrLogger;

use function date;

class LogServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the log config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof LogConfigContract) {
            $container->setSingleton(LogConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            LogConfigContract::class,
            new LogConfig()
        );
    }

    /**
     * Publish the psr logger config service.
     */
    public static function publishPsrConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof LogPsrConfigContract) {
            $container->setSingleton(LogPsrConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            LogPsrConfigContract::class,
            new LogPsrConfig()
        );
    }

    /**
     * Publish the logger service.
     */
    public static function publishLogger(ContainerContract $container): void
    {
        $config = $container->getSingleton(LogConfigContract::class);

        $container->setSingleton(
            LoggerContract::class,
            $container->getSingleton($config->defaultLogger),
        );
    }

    /**
     * Publish the psr adapter service.
     */
    public static function publishPsrLogger(ContainerContract $container): void
    {
        $container->setSingleton(
            PsrLogger::class,
            new PsrLogger(
                $container->getSingleton(LoggerInterface::class),
            ),
        );
    }

    /**
     * Publish the null adapter service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishNullLogger(ContainerContract $container): void
    {
        $container->setSingleton(
            NullLogger::class,
            new NullLogger(),
        );
    }

    /**
     * Publish the psr logger interface.
     */
    public static function publishLoggerInterface(ContainerContract $container): void
    {
        $container->setSingleton(
            LoggerInterface::class,
            $container->getSingleton(Logger::class),
        );
    }

    /**
     * Publish the Logger service.
     */
    public static function publishMonolog(ContainerContract $container): void
    {
        $config = $container->getSingleton(LogPsrConfigContract::class);

        $filePath = $config->psrFilePath ?? Directory::logsStoragePath();
        $name     = $config->psrName ?? 'valkyrja' . date('-Y-m-d');

        $handler = new StreamHandler(
            "$filePath/$name.log",
            $config->psrLevel->name
        );

        $formatter = new LineFormatter(
            null,
            null,
            true,
            true
        );

        $handler->setFormatter($formatter);

        $container->setSingleton(
            Logger::class,
            new Logger(
                $name,
                [
                    $handler,
                ]
            )
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            LogConfigContract::class    => [self::class, 'publishConfig'],
            LogPsrConfigContract::class => [self::class, 'publishPsrConfig'],
            LoggerContract::class       => [self::class, 'publishLogger'],
            PsrLogger::class            => [self::class, 'publishPsrLogger'],
            NullLogger::class           => [self::class, 'publishNullLogger'],
            LoggerInterface::class      => [self::class, 'publishLoggerInterface'],
            Logger::class               => [self::class, 'publishMonolog'],
        ];
    }
}
