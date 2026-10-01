<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cli\Server\Provider;

use PHPUnit\Framework\MockObject\Exception;
use ReflectionProperty;
use Valkyrja\Application\Data\CliConfig;
use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Cli\Interaction\Data\CliInteractionConfig;
use Valkyrja\Cli\Interaction\Data\Contract\CliInteractionConfigContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Middleware\Handler\Contract\InputReceivedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ProcessExitingHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Constant\OptionName;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Cli\Server\Command\HelpCommand;
use Valkyrja\Cli\Server\Command\ListBashCommand;
use Valkyrja\Cli\Server\Command\ListCommand;
use Valkyrja\Cli\Server\Command\VersionCommand;
use Valkyrja\Cli\Server\Constant\CommandName;
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
use Valkyrja\Cli\Server\Provider\CliServerServiceProvider;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Cli\Server\Data\CliCommandCommandConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = CliServerServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(CliHelpCommandConfigContract::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(CliVersionCommandConfigContract::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(CliNoInteractionConfigContract::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(CliQuietInteractionConfigContract::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(CliSilentInteractionConfigContract::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(InputHandlerContract::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(HelpCommand::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(ListBashCommand::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(ListCommand::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(VersionCommand::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(LogThrowableCaughtMiddleware::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(OutputThrowableCaughtMiddleware::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(CheckForHelpOptionsMiddleware::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(CheckForVersionOptionsMiddleware::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(CheckGlobalInteractionOptionsMiddleware::class, new CliServerServiceProvider()->publishers());
        self::assertArrayHasKey(CheckCommandForTypoMiddleware::class, new CliServerServiceProvider()->publishers());
    }

    public function testPublishHelpCommandConfig(): void
    {
        $callback = new CliServerServiceProvider()->publishers()[CliHelpCommandConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CliHelpCommandConfig::class, $config = $this->container->getSingleton(CliHelpCommandConfigContract::class));
        self::assertSame(CommandName::HELP, $config->helpCommandName);
    }

    public function testPublishHelpCommandConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new CliCommandCommandConfigFixture(helpCommandName: 'helpTest'));

        $callback = new CliServerServiceProvider()->publishers()[CliHelpCommandConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(CliHelpCommandConfigContract::class));
        self::assertSame('helpTest', $config->helpCommandName);
    }

    public function testPublishVersionCommandConfig(): void
    {
        $callback = new CliServerServiceProvider()->publishers()[CliVersionCommandConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CliVersionCommandConfig::class, $config = $this->container->getSingleton(CliVersionCommandConfigContract::class));
        self::assertSame(CommandName::VERSION, $config->versionCommandName);
    }

    public function testPublishVersionCommandConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new CliCommandCommandConfigFixture(versionCommandName: 'versionTest'));

        $callback = new CliServerServiceProvider()->publishers()[CliVersionCommandConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(CliVersionCommandConfigContract::class));
        self::assertSame('versionTest', $config->versionCommandName);
    }

    public function testPublishNoInteractionConfig(): void
    {
        $callback = new CliServerServiceProvider()->publishers()[CliNoInteractionConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CliNoInteractionConfig::class, $config = $this->container->getSingleton(CliNoInteractionConfigContract::class));
        self::assertSame(OptionName::NO_INTERACTION, $config->noInteractionOptionName);
    }

    public function testPublishNoInteractionConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new CliCommandCommandConfigFixture(noInteractionOptionName: 'batchTest'));

        $callback = new CliServerServiceProvider()->publishers()[CliNoInteractionConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(CliNoInteractionConfigContract::class));
        self::assertSame('batchTest', $config->noInteractionOptionName);
    }

    public function testPublishQuietInteractionConfig(): void
    {
        $callback = new CliServerServiceProvider()->publishers()[CliQuietInteractionConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CliQuietInteractionConfig::class, $config = $this->container->getSingleton(CliQuietInteractionConfigContract::class));
        self::assertSame(OptionName::QUIET, $config->quietOptionName);
    }

    public function testPublishQuietInteractionConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new CliCommandCommandConfigFixture(quietOptionName: 'hushTest'));

        $callback = new CliServerServiceProvider()->publishers()[CliQuietInteractionConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(CliQuietInteractionConfigContract::class));
        self::assertSame('hushTest', $config->quietOptionName);
    }

    public function testPublishSilentInteractionConfig(): void
    {
        $callback = new CliServerServiceProvider()->publishers()[CliSilentInteractionConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(CliSilentInteractionConfig::class, $config = $this->container->getSingleton(CliSilentInteractionConfigContract::class));
        self::assertSame(OptionName::SILENT, $config->silentOptionName);
    }

    public function testPublishSilentInteractionConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new CliCommandCommandConfigFixture(silentOptionName: 'muteTest'));

        $callback = new CliServerServiceProvider()->publishers()[CliSilentInteractionConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(CliSilentInteractionConfigContract::class));
        self::assertSame('muteTest', $config->silentOptionName);
    }

    /**
     * @throws Exception
     */
    public function testPublishInputHandler(): void
    {
        $this->container->setSingleton(CliInteractionConfigContract::class, self::createStub(CliInteractionConfig::class));
        $this->container->setSingleton(RouterContract::class, self::createStub(RouterContract::class));
        $this->container->setSingleton(InputReceivedHandlerContract::class, self::createStub(InputReceivedHandlerContract::class));
        $this->container->setSingleton(ThrowableCaughtHandlerContract::class, self::createStub(ThrowableCaughtHandlerContract::class));
        $this->container->setSingleton(ProcessExitingHandlerContract::class, self::createStub(ProcessExitingHandlerContract::class));

        $callback = new CliServerServiceProvider()->publishers()[InputHandlerContract::class];
        $callback($this->container);

        self::assertInstanceOf(InputHandler::class, $this->container->getSingleton(InputHandlerContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishHelpCommand(): void
    {
        $this->container->setSingleton(CliConfigContract::class, new CliConfig());
        $this->container->setSingleton(RouteContract::class, self::createStub(RouteContract::class));
        $this->container->setSingleton(RouteCollectionContract::class, self::createStub(RouteCollectionContract::class));
        $this->container->setSingleton(OutputFactoryContract::class, self::createStub(OutputFactoryContract::class));

        $callback = new CliServerServiceProvider()->publishers()[HelpCommand::class];
        $callback($this->container);

        self::assertInstanceOf(HelpCommand::class, $this->container->getSingleton(HelpCommand::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishListBashCommand(): void
    {
        $this->container->setSingleton(RouteContract::class, self::createStub(RouteContract::class));
        $this->container->setSingleton(RouteCollectionContract::class, self::createStub(RouteCollectionContract::class));
        $this->container->setSingleton(OutputFactoryContract::class, self::createStub(OutputFactoryContract::class));

        $callback = new CliServerServiceProvider()->publishers()[ListBashCommand::class];
        $callback($this->container);

        self::assertInstanceOf(ListBashCommand::class, $this->container->getSingleton(ListBashCommand::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishListCommand(): void
    {
        $this->container->setSingleton(CliConfigContract::class, new CliConfig());
        $this->container->setSingleton(RouteContract::class, self::createStub(RouteContract::class));
        $this->container->setSingleton(RouteCollectionContract::class, self::createStub(RouteCollectionContract::class));
        $this->container->setSingleton(OutputFactoryContract::class, self::createStub(OutputFactoryContract::class));

        $callback = new CliServerServiceProvider()->publishers()[ListCommand::class];
        $callback($this->container);

        self::assertInstanceOf(ListCommand::class, $this->container->getSingleton(ListCommand::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishVersionCommand(): void
    {
        $this->container->setSingleton(OutputFactoryContract::class, self::createStub(OutputFactoryContract::class));
        $this->container->setSingleton(CliConfigContract::class, new CliConfig());
        $this->container->setSingleton(RouteContract::class, self::createStub(RouteContract::class));

        $callback = new CliServerServiceProvider()->publishers()[VersionCommand::class];
        $callback($this->container);

        self::assertInstanceOf(VersionCommand::class, $this->container->getSingleton(VersionCommand::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishLogThrowableCaughtMiddleware(): void
    {
        $this->container->setSingleton(LoggerContract::class, self::createStub(LoggerContract::class));

        $callback = new CliServerServiceProvider()->publishers()[LogThrowableCaughtMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(LogThrowableCaughtMiddleware::class, $this->container->getSingleton(LogThrowableCaughtMiddleware::class));
    }

    public function testPublishOutputThrowableCaughtMiddleware(): void
    {
        $callback = new CliServerServiceProvider()->publishers()[OutputThrowableCaughtMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(OutputThrowableCaughtMiddleware::class, $this->container->getSingleton(OutputThrowableCaughtMiddleware::class));
    }

    public function testPublishCheckForHelpOptionsMiddleware(): void
    {
        CliServerServiceProvider::publishHelpCommandConfig($this->container);

        $callback = new CliServerServiceProvider()->publishers()[CheckForHelpOptionsMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(CheckForHelpOptionsMiddleware::class, $this->container->getSingleton(CheckForHelpOptionsMiddleware::class));
    }

    public function testPublishCheckForHelpOptionsMiddlewareWithCustomConfig(): void
    {
        $this->container->setSingleton(
            ConfigContract::class,
            $config = new CliCommandCommandConfigFixture(
                helpCommandName: 'helpTest',
                helpOptionName: 'helpOptionNameTest',
                helpOptionShortName: 'helpOptionShortNameTest',
            )
        );
        CliServerServiceProvider::publishHelpCommandConfig($this->container);

        $callback = new CliServerServiceProvider()->publishers()[CheckForHelpOptionsMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(CheckForHelpOptionsMiddleware::class, $middleware = $this->container->getSingleton(CheckForHelpOptionsMiddleware::class));

        $reflection  = new ReflectionProperty($middleware, 'commandName');
        $commandName = $reflection->getValue($middleware);

        $reflection = new ReflectionProperty($middleware, 'optionName');
        $optionName = $reflection->getValue($middleware);

        $reflection      = new ReflectionProperty($middleware, 'optionShortName');
        $optionShortName = $reflection->getValue($middleware);

        self::assertSame($config->helpCommandName, $commandName);
        self::assertSame($config->helpOptionName, $optionName);
        self::assertSame($config->helpOptionShortName, $optionShortName);
    }

    public function testPublishCheckForVersionOptionsMiddleware(): void
    {
        CliServerServiceProvider::publishVersionCommandConfig($this->container);

        $callback = new CliServerServiceProvider()->publishers()[CheckForVersionOptionsMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(CheckForVersionOptionsMiddleware::class, $this->container->getSingleton(CheckForVersionOptionsMiddleware::class));
    }

    public function testPublishCheckForVersionOptionsMiddlewareWithCustomConfig(): void
    {
        $this->container->setSingleton(
            ConfigContract::class,
            $config = new CliCommandCommandConfigFixture(
                versionCommandName: 'versionTest',
                versionOptionName: 'versionOptionNameTest',
                versionOptionShortName: 'versionOptionShortNameTest',
            )
        );
        CliServerServiceProvider::publishVersionCommandConfig($this->container);

        $callback = new CliServerServiceProvider()->publishers()[CheckForVersionOptionsMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(CheckForVersionOptionsMiddleware::class, $middleware = $this->container->getSingleton(CheckForVersionOptionsMiddleware::class));

        $reflection  = new ReflectionProperty($middleware, 'commandName');
        $commandName = $reflection->getValue($middleware);

        $reflection = new ReflectionProperty($middleware, 'optionName');
        $optionName = $reflection->getValue($middleware);

        $reflection      = new ReflectionProperty($middleware, 'optionShortName');
        $optionShortName = $reflection->getValue($middleware);

        self::assertSame($config->versionCommandName, $commandName);
        self::assertSame($config->versionOptionName, $optionName);
        self::assertSame($config->versionOptionShortName, $optionShortName);
    }

    public function testPublishCheckGlobalInteractionOptionsMiddleware(): void
    {
        CliServerServiceProvider::publishNoInteractionConfig($this->container);
        CliServerServiceProvider::publishQuietInteractionConfig($this->container);
        CliServerServiceProvider::publishSilentInteractionConfig($this->container);

        $this->container->setSingleton(CliInteractionConfigContract::class, self::createStub(CliInteractionConfig::class));

        $callback = new CliServerServiceProvider()->publishers()[CheckGlobalInteractionOptionsMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(CheckGlobalInteractionOptionsMiddleware::class, $this->container->getSingleton(CheckGlobalInteractionOptionsMiddleware::class));
    }

    public function testPublishCheckGlobalInteractionOptionsMiddlewareWithCustomConfig(): void
    {
        $this->container->setSingleton(
            ConfigContract::class,
            $config = new CliCommandCommandConfigFixture(
                noInteractionOptionName: 'noInteractionOptionNameTest',
                noInteractionOptionShortName: 'noInteractionOptionShortNameTest',
                quietOptionName: 'quietOptionNameTest',
                quietOptionShortName: 'quietOptionShortNameTest',
                silentOptionName: 'silentOptionNameTest',
                silentOptionShortName: 'silentOptionShortNameTest',
            )
        );
        CliServerServiceProvider::publishNoInteractionConfig($this->container);
        CliServerServiceProvider::publishQuietInteractionConfig($this->container);
        CliServerServiceProvider::publishSilentInteractionConfig($this->container);
        $this->container->setSingleton(CliInteractionConfigContract::class, self::createStub(CliInteractionConfig::class));

        $callback = new CliServerServiceProvider()->publishers()[CheckGlobalInteractionOptionsMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(CheckGlobalInteractionOptionsMiddleware::class, $middleware = $this->container->getSingleton(CheckGlobalInteractionOptionsMiddleware::class));

        $reflection              = new ReflectionProperty($middleware, 'noInteractionOptionName');
        $noInteractionOptionName = $reflection->getValue($middleware);

        $reflection                   = new ReflectionProperty($middleware, 'noInteractionOptionShortName');
        $noInteractionOptionShortName = $reflection->getValue($middleware);

        self::assertSame($config->noInteractionOptionName, $noInteractionOptionName);
        self::assertSame($config->noInteractionOptionShortName, $noInteractionOptionShortName);

        $reflection      = new ReflectionProperty($middleware, 'quietOptionName');
        $quietOptionName = $reflection->getValue($middleware);

        $reflection           = new ReflectionProperty($middleware, 'quietOptionShortName');
        $quietOptionShortName = $reflection->getValue($middleware);

        self::assertSame($config->quietOptionName, $quietOptionName);
        self::assertSame($config->quietOptionShortName, $quietOptionShortName);

        $reflection       = new ReflectionProperty($middleware, 'silentOptionName');
        $silentOptionName = $reflection->getValue($middleware);

        $reflection            = new ReflectionProperty($middleware, 'silentOptionShortName');
        $silentOptionShortName = $reflection->getValue($middleware);

        self::assertSame($config->silentOptionName, $silentOptionName);
        self::assertSame($config->silentOptionShortName, $silentOptionShortName);
    }

    /**
     * @throws Exception
     */
    public function testPublishCheckCommandForTypoMiddleware(): void
    {
        $this->container->setSingleton(RouterContract::class, self::createStub(RouterContract::class));
        $this->container->setSingleton(RouteCollectionContract::class, self::createStub(RouteCollectionContract::class));

        $callback = new CliServerServiceProvider()->publishers()[CheckCommandForTypoMiddleware::class];
        $callback($this->container);

        self::assertInstanceOf(CheckCommandForTypoMiddleware::class, $this->container->getSingleton(CheckCommandForTypoMiddleware::class));
    }
}
