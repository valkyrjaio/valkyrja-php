<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\Abstract;

use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Data\Contract\HttpConfigContract;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Kernel\Valkyrja;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\ContainerServiceProvider;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Throwable\Handler\Contract\ThrowableHandlerContract;
use Valkyrja\Throwable\Handler\WhoopsThrowableHandler;

use function define;
use function defined;

abstract class App
{
    /**
     * Start the application.
     */
    public static function start(ConfigContract $config): ApplicationContract
    {
        if ($config->debugMode) {
            static::defaultExceptionHandler();
        }

        static::appStart();
        static::directory(dir: $config->dir);

        return static::app(config: $config);
    }

    /**
     * Set a global constant for when the application as a whole started.
     */
    public static function appStart(): void
    {
        if (! defined('APP_START')) {
            define('APP_START', Microtime::get());
        }
    }

    /**
     * Let's set the base directory within the web server for our application
     * so that when we locate directories and files within the application
     * we have a standard location from which to do so.
     *
     * @param non-empty-string $dir The directory
     */
    public static function directory(string $dir): void
    {
        Directory::$basePath = $dir;
    }

    /**
     * Let's start up the application by creating a new instance of the
     * application class. This is going to bind all the various
     * components together into a singular hub. This will set the
     *  correct environment class file to use, and appropriate the config
     *  that should be loaded by the application. In dev you'll want to
     *  use the default config out of the root config directory, but
     *  when you're on a production environment definitely have
     *  your config cached and the flag set in your env class.
     */
    public static function app(ConfigContract $config): ApplicationContract
    {
        $container = static::getContainer();
        $app       = static::getApplication(container: $container, config: $config);

        static::bootstrapServices(
            app: $app,
            container: $container,
            config: $config
        );

        return $app;
    }

    /**
     * Get the application.
     */
    public static function getApplication(ContainerContract $container, ConfigContract $config): ApplicationContract
    {
        return new Valkyrja(
            container: $container,
            config: $config,
        );
    }

    /**
     * Bootstrap container services.
     */
    public static function bootstrapServices(ApplicationContract $app, ContainerContract $container, ConfigContract $config): void
    {
        $container->setSingleton(ConfigContract::class, $config);
        $container->setSingleton($config::class, $config);
        $container->setSingleton(ContainerContract::class, $container);
        $container->setSingleton(ApplicationContract::class, $app);

        if ($config instanceof CliConfigContract) {
            $container->setSingleton(CliConfigContract::class, $config);
        }

        if ($config instanceof HttpConfigContract) {
            $container->setSingleton(HttpConfigContract::class, $config);
        }

        if ($config instanceof QueueConfigContract) {
            $container->setSingleton(QueueConfigContract::class, $config);
        }

        $app->publishProviderCallbacks();

        static::loadContainerData(container: $container);
    }

    /**
     * Load container data.
     */
    public static function loadContainerData(ContainerContract $container): void
    {
        if (! $container->isSingleton(ContainerData::class)) {
            self::publishContainerData(container: $container);
        }

        $containerData = $container->getSingleton(ContainerData::class);

        $container->setFromData($containerData);
    }

    /**
     * Publish the container data.
     */
    public static function publishContainerData(ContainerContract $container): void
    {
        ContainerServiceProvider::publishData(container: $container);
    }

    /**
     * Set a default exception handler until the one specified in config is set in the Container\AppProvider.
     */
    public static function defaultExceptionHandler(): void
    {
        new WhoopsThrowableHandler()->enable(
            displayErrors: true
        );
    }

    /**
     * Bootstrap throwable handler.
     */
    public static function bootstrapThrowableHandler(ApplicationContract $app, ContainerContract $container): void
    {
        // If debug is on, enable debug handling
        if ($app->getDebugMode()) {
            $errorHandler = static::getThrowableHandler();

            // Set error handler in the service container
            $container->setSingleton(ThrowableHandlerContract::class, $errorHandler);

            // Enable error handling
            $errorHandler->enable(
                displayErrors: true
            );
        }
    }

    /**
     * Get the throwable handler.
     */
    public static function getThrowableHandler(): ThrowableHandlerContract
    {
        return new WhoopsThrowableHandler();
    }

    /**
     * Get the container.
     */
    public static function getContainer(): ContainerContract
    {
        return new Container();
    }
}
