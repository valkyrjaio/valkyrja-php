<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Server\Provider;

use Override;
use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Cli\Interaction\Data\Contract\CliInteractionConfigContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Middleware\Handler\Contract\InputReceivedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ProcessExitingHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Cli\Server\Command\HelpCommand;
use Valkyrja\Cli\Server\Command\ListBashCommand;
use Valkyrja\Cli\Server\Command\ListCommand;
use Valkyrja\Cli\Server\Command\VersionCommand;
use Valkyrja\Cli\Server\Data\CliHelpCommandConfig;
use Valkyrja\Cli\Server\Data\CliNoInteractionConfig;
use Valkyrja\Cli\Server\Data\CliQuietInteractionConfig;
use Valkyrja\Cli\Server\Data\CliSilentInteractionConfig;
use Valkyrja\Cli\Server\Data\CliVersionCommandConfig;
use Valkyrja\Cli\Server\Data\Contract\CliHelpCommandConfigContract;
use Valkyrja\Cli\Server\Data\Contract\CliNoInteractionConfigContract;
use Valkyrja\Cli\Server\Data\Contract\CliQuietInteractionConfigContract;
use Valkyrja\Cli\Server\Data\Contract\CliSilentInteractionConfigContract;
use Valkyrja\Cli\Server\Data\Contract\CliVersionCommandConfigContract;
use Valkyrja\Cli\Server\Handler\Contract\InputHandlerContract;
use Valkyrja\Cli\Server\Handler\InputHandler;
use Valkyrja\Cli\Server\Middleware\InputReceived\CheckForHelpOptionsMiddleware;
use Valkyrja\Cli\Server\Middleware\InputReceived\CheckForVersionOptionsMiddleware;
use Valkyrja\Cli\Server\Middleware\InputReceived\CheckGlobalInteractionOptionsMiddleware;
use Valkyrja\Cli\Server\Middleware\RouteNotMatched\CheckCommandForTypoMiddleware;
use Valkyrja\Cli\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware;
use Valkyrja\Cli\Server\Middleware\ThrowableCaught\OutputThrowableCaughtMiddleware;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;

class CliServerServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the help command config service.
     */
    public static function publishHelpCommandConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CliHelpCommandConfigContract) {
            $container->setSingleton(CliHelpCommandConfigContract::class, $config);

            return;
        }

        $container->setSingleton(CliHelpCommandConfigContract::class, new CliHelpCommandConfig());
    }

    /**
     * Publish the version command config service.
     */
    public static function publishVersionCommandConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CliVersionCommandConfigContract) {
            $container->setSingleton(CliVersionCommandConfigContract::class, $config);

            return;
        }

        $container->setSingleton(CliVersionCommandConfigContract::class, new CliVersionCommandConfig());
    }

    /**
     * Publish the no interaction config service.
     */
    public static function publishNoInteractionConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CliNoInteractionConfigContract) {
            $container->setSingleton(CliNoInteractionConfigContract::class, $config);

            return;
        }

        $container->setSingleton(CliNoInteractionConfigContract::class, new CliNoInteractionConfig());
    }

    /**
     * Publish the quiet interaction config service.
     */
    public static function publishQuietInteractionConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CliQuietInteractionConfigContract) {
            $container->setSingleton(CliQuietInteractionConfigContract::class, $config);

            return;
        }

        $container->setSingleton(CliQuietInteractionConfigContract::class, new CliQuietInteractionConfig());
    }

    /**
     * Publish the silent interaction config service.
     */
    public static function publishSilentInteractionConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof CliSilentInteractionConfigContract) {
            $container->setSingleton(CliSilentInteractionConfigContract::class, $config);

            return;
        }

        $container->setSingleton(CliSilentInteractionConfigContract::class, new CliSilentInteractionConfig());
    }

    /**
     * Publish the input handler service.
     */
    public static function publishInputHandler(ContainerContract $container): void
    {
        $config = $container->getSingleton(CliInteractionConfigContract::class);

        $container->setSingleton(
            InputHandlerContract::class,
            new InputHandler(
                container: $container,
                router: $container->getSingleton(RouterContract::class),
                inputReceivedHandler: $container->getSingleton(InputReceivedHandlerContract::class),
                throwableCaughtHandler: $container->getSingleton(ThrowableCaughtHandlerContract::class),
                processExitingHandler: $container->getSingleton(ProcessExitingHandlerContract::class),
                interactionConfig: $config
            ),
        );
    }

    /**
     * Publish the HelpCommand service.
     */
    public static function publishHelpCommand(ContainerContract $container): void
    {
        $container->setSingleton(
            HelpCommand::class,
            new HelpCommand(
                config: $container->getSingleton(CliConfigContract::class),
                route: $container->getSingleton(RouteContract::class),
                collection: $container->getSingleton(RouteCollectionContract::class),
                outputFactory: $container->getSingleton(OutputFactoryContract::class),
            )
        );
    }

    /**
     * Publish the HelpCommand service.
     */
    public static function publishListBashCommand(ContainerContract $container): void
    {
        $container->setSingleton(
            ListBashCommand::class,
            new ListBashCommand(
                route: $container->getSingleton(RouteContract::class),
                collection: $container->getSingleton(RouteCollectionContract::class),
                outputFactory: $container->getSingleton(OutputFactoryContract::class),
            )
        );
    }

    /**
     * Publish the ListCommand service.
     */
    public static function publishListCommand(ContainerContract $container): void
    {
        $container->setSingleton(
            ListCommand::class,
            new ListCommand(
                config: $container->getSingleton(CliConfigContract::class),
                route: $container->getSingleton(RouteContract::class),
                collection: $container->getSingleton(RouteCollectionContract::class),
                outputFactory: $container->getSingleton(OutputFactoryContract::class),
            )
        );
    }

    /**
     * Publish the VersionCommand service.
     */
    public static function publishVersionCommand(ContainerContract $container): void
    {
        $container->setSingleton(
            VersionCommand::class,
            new VersionCommand(
                outputFactory: $container->getSingleton(OutputFactoryContract::class),
                config: $container->getSingleton(CliConfigContract::class),
                route: $container->getSingleton(RouteContract::class),
            )
        );
    }

    /**
     * Publish the LogThrowableCaughtMiddleware service.
     */
    public static function publishLogThrowableCaughtMiddleware(ContainerContract $container): void
    {
        $container->setSingleton(
            LogThrowableCaughtMiddleware::class,
            new LogThrowableCaughtMiddleware(
                logger: $container->getSingleton(LoggerContract::class),
            )
        );
    }

    /**
     * Publish the OutputThrowableCaughtMiddleware service.
     */
    public static function publishOutputThrowableCaughtMiddleware(ContainerContract $container): void
    {
        $container->setSingleton(
            OutputThrowableCaughtMiddleware::class,
            new OutputThrowableCaughtMiddleware()
        );
    }

    /**
     * Publish the CheckForHelpOptionsMiddleware service.
     */
    public static function publishCheckForHelpOptionsMiddleware(ContainerContract $container): void
    {
        $config = $container->getSingleton(CliHelpCommandConfigContract::class);

        $container->setSingleton(
            CheckForHelpOptionsMiddleware::class,
            new CheckForHelpOptionsMiddleware(
                commandName: $config->helpCommandName,
                optionName: $config->helpOptionName,
                optionShortName: $config->helpOptionShortName
            )
        );
    }

    /**
     * Publish the CheckForVersionOptionsMiddleware service.
     */
    public static function publishCheckForVersionOptionsMiddleware(ContainerContract $container): void
    {
        $config = $container->getSingleton(CliVersionCommandConfigContract::class);

        $container->setSingleton(
            CheckForVersionOptionsMiddleware::class,
            new CheckForVersionOptionsMiddleware(
                commandName: $config->versionCommandName,
                optionName: $config->versionOptionName,
                optionShortName: $config->versionOptionShortName
            )
        );
    }

    /**
     * Publish the CheckGlobalInteractionOptionsMiddleware service.
     */
    public static function publishCheckGlobalInteractionOptionsMiddleware(ContainerContract $container): void
    {
        $noInteractionConfig = $container->getSingleton(CliNoInteractionConfigContract::class);
        $quietConfig         = $container->getSingleton(CliQuietInteractionConfigContract::class);
        $silentConfig        = $container->getSingleton(CliSilentInteractionConfigContract::class);

        $container->setSingleton(
            CheckGlobalInteractionOptionsMiddleware::class,
            new CheckGlobalInteractionOptionsMiddleware(
                config: $container->getSingleton(CliInteractionConfigContract::class),
                noInteractionOptionName: $noInteractionConfig->noInteractionOptionName,
                noInteractionOptionShortName: $noInteractionConfig->noInteractionOptionShortName,
                quietOptionName: $quietConfig->quietOptionName,
                quietOptionShortName: $quietConfig->quietOptionShortName,
                silentOptionName: $silentConfig->silentOptionName,
                silentOptionShortName: $silentConfig->silentOptionShortName
            )
        );
    }

    /**
     * Publish the check command for typo middleware service.
     */
    public static function publishCheckCommandForTypoMiddleware(ContainerContract $container): void
    {
        $container->setSingleton(
            CheckCommandForTypoMiddleware::class,
            new CheckCommandForTypoMiddleware(
                router: $container->getSingleton(RouterContract::class),
                collection: $container->getSingleton(RouteCollectionContract::class),
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
            CliHelpCommandConfigContract::class            => [self::class, 'publishHelpCommandConfig'],
            CliVersionCommandConfigContract::class         => [self::class, 'publishVersionCommandConfig'],
            CliNoInteractionConfigContract::class          => [self::class, 'publishNoInteractionConfig'],
            CliQuietInteractionConfigContract::class       => [self::class, 'publishQuietInteractionConfig'],
            CliSilentInteractionConfigContract::class      => [self::class, 'publishSilentInteractionConfig'],
            InputHandlerContract::class                    => [self::class, 'publishInputHandler'],
            HelpCommand::class                             => [self::class, 'publishHelpCommand'],
            ListBashCommand::class                         => [self::class, 'publishListBashCommand'],
            ListCommand::class                             => [self::class, 'publishListCommand'],
            VersionCommand::class                          => [self::class, 'publishVersionCommand'],
            LogThrowableCaughtMiddleware::class            => [self::class, 'publishLogThrowableCaughtMiddleware'],
            OutputThrowableCaughtMiddleware::class         => [self::class, 'publishOutputThrowableCaughtMiddleware'],
            CheckForHelpOptionsMiddleware::class           => [self::class, 'publishCheckForHelpOptionsMiddleware'],
            CheckForVersionOptionsMiddleware::class        => [self::class, 'publishCheckForVersionOptionsMiddleware'],
            CheckGlobalInteractionOptionsMiddleware::class => [self::class, 'publishCheckGlobalInteractionOptionsMiddleware'],
            CheckCommandForTypoMiddleware::class           => [self::class, 'publishCheckCommandForTypoMiddleware'],
        ];
    }
}
