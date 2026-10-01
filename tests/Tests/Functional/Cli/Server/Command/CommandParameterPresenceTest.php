<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Functional\Cli\Server\Command;

use Valkyrja\Application\Data\CliConfig;
use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Attribute\Collector\Collector;
use Valkyrja\Cli\Interaction\Data\CliInteractionConfig;
use Valkyrja\Cli\Interaction\Data\Contract\CliInteractionConfigContract;
use Valkyrja\Cli\Interaction\Input\Contract\InputContract;
use Valkyrja\Cli\Interaction\Input\Factory\InputFactory;
use Valkyrja\Cli\Interaction\Message\Contract\MessageContract;
use Valkyrja\Cli\Interaction\Message\Header;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Interaction\Output\Factory\OutputFactory;
use Valkyrja\Cli\Middleware\Handler\InputReceivedHandler;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Collection\RouteCollection;
use Valkyrja\Cli\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Cli\Routing\Dispatcher\Router;
use Valkyrja\Cli\Server\Command\ListBashCommand;
use Valkyrja\Cli\Server\Command\ListCommand;
use Valkyrja\Cli\Server\Command\VersionCommand;
use Valkyrja\Cli\Server\Data\Contract\CliHelpCommandConfigContract;
use Valkyrja\Cli\Server\Data\Contract\CliNoInteractionConfigContract;
use Valkyrja\Cli\Server\Data\Contract\CliQuietInteractionConfigContract;
use Valkyrja\Cli\Server\Data\Contract\CliSilentInteractionConfigContract;
use Valkyrja\Cli\Server\Data\Contract\CliVersionCommandConfigContract;
use Valkyrja\Cli\Server\Middleware\InputReceived\CheckForHelpOptionsMiddleware;
use Valkyrja\Cli\Server\Middleware\InputReceived\CheckForVersionOptionsMiddleware;
use Valkyrja\Cli\Server\Middleware\InputReceived\CheckGlobalInteractionOptionsMiddleware;
use Valkyrja\Cli\Server\Provider\CliServerServiceProvider;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Reflection\Reflector\Reflector;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function array_map;
use function implode;

final class CommandParameterPresenceTest extends TestCase
{
    /** @var non-empty-string */
    private const string APP_NAMESPACE = 'PresenceApp';

    /** @var non-empty-string */
    private const string APP_VERSION = '9.8.7';

    /**
     * A bare `version` shows the banner, because neither flag was spelled.
     */
    public function testVersionWithoutFlagsShowsTheBanner(): void
    {
        $output = $this->dispatch(['cli', 'version']);

        self::assertCount(1, $output->getMessages());
        self::assertInstanceOf(Header::class, $output->getMessages()[0]);
    }

    /**
     * `--short` shows the version number by itself.
     */
    public function testVersionShortShowsTheVersionOnly(): void
    {
        $output = $this->dispatch(['cli', 'version', '--short']);

        self::assertSame(expected: self::APP_VERSION, actual: $this->text($output));
    }

    /**
     * `--plain` shows the version info without the banner.
     */
    public function testVersionPlainShowsTheInfoWithoutTheBanner(): void
    {
        $text = $this->text($this->dispatch(['cli', 'version', '--plain']));

        self::assertStringContainsString(
            needle: self::APP_NAMESPACE . ' v' . self::APP_VERSION,
            haystack: $text
        );
        self::assertStringContainsString(needle: 'Built on Valkyrja v', haystack: $text);
        self::assertStringNotContainsString(needle: '╭──', haystack: $text);
    }

    /**
     * The global `--version` flag routes to the version command with no option
     * spelled, so it renders the same banner that a bare `version` renders.
     */
    public function testGlobalVersionFlagRoutesToTheBareVersionCommand(): void
    {
        $output = $this->dispatch(['cli', '--version'], withInputMiddleware: true);

        self::assertCount(1, $output->getMessages());
        self::assertInstanceOf(Header::class, $output->getMessages()[0]);
    }

    /**
     * The short spelling of the global flag routes the same way.
     */
    public function testGlobalVersionShortFlagRoutesToTheBareVersionCommand(): void
    {
        $output = $this->dispatch(['cli', '-v'], withInputMiddleware: true);

        self::assertCount(1, $output->getMessages());
        self::assertInstanceOf(Header::class, $output->getMessages()[0]);
    }

    /**
     * A bare `list` filters nothing, so every command stays in the output.
     */
    public function testListWithoutTheNamespaceOptionListsEveryCommand(): void
    {
        $text = $this->text($this->dispatch(['cli', 'list']));

        self::assertStringContainsString(needle: 'Commands:', haystack: $text);
        self::assertStringContainsString(needle: 'version', haystack: $text);
        self::assertStringContainsString(needle: 'list:bash', haystack: $text);
    }

    /**
     * `--namespace` filters the output down to the commands under that namespace.
     */
    public function testListWithTheNamespaceOptionFilters(): void
    {
        $text = $this->text($this->dispatch(['cli', 'list', '--namespace=list']));

        self::assertStringContainsString(needle: 'Commands [list]:', haystack: $text);
        self::assertStringContainsString(needle: 'list:bash', haystack: $text);
        self::assertStringNotContainsString(needle: 'version', haystack: $text);
    }

    /**
     * `list:bash` with only the application-name argument filters nothing.
     */
    public function testListBashWithoutTheNamespaceArgumentListsEveryCommand(): void
    {
        $text = $this->text($this->dispatch(['cli', 'list:bash', 'cli']));

        self::assertStringContainsString(needle: 'version', haystack: $text);
        self::assertStringContainsString(needle: 'list:bash', haystack: $text);
    }

    /**
     * A spelled namespace argument filters, and the colon trims the prefix.
     */
    public function testListBashWithTheNamespaceArgumentFilters(): void
    {
        $text = $this->text($this->dispatch(['cli', 'list:bash', 'cli', 'list:']));

        self::assertSame(expected: 'bash', actual: $text);
    }

    /**
     * Dispatch an argv array through the real router and return the output.
     *
     * @param non-empty-string[] $argv                The argv
     * @param bool               $withInputMiddleware Run the default input-received middleware first
     */
    private function dispatch(array $argv, bool $withInputMiddleware = false): OutputContract
    {
        $reflector      = new Reflector();
        $routeCollector = new AttributeRouteCollector(
            attributes: new Collector($reflector),
            reflection: $reflector
        );

        $collection = new RouteCollection();
        $collection->add(
            ...$routeCollector->getRoutes(
                VersionCommand::class,
                ListCommand::class,
                ListBashCommand::class
            )
        );

        $outputFactory = new OutputFactory();
        $config        = new CliConfig(namespace: self::APP_NAMESPACE, version: self::APP_VERSION);

        $container = new Container();
        $container->setSingleton(CliConfigContract::class, $config);
        $container->setSingleton(ConfigContract::class, $config);
        $container->setSingleton(CliInteractionConfigContract::class, new CliInteractionConfig());
        $container->setSingleton(OutputFactoryContract::class, $outputFactory);
        $container->setSingleton(RouteCollectionContract::class, $collection);
        $container->setFromData(
            new ContainerData(
                callbacks: [
                    CliHelpCommandConfigContract::class            => [CliServerServiceProvider::class, 'publishHelpCommandConfig'],
                    CliVersionCommandConfigContract::class         => [CliServerServiceProvider::class, 'publishVersionCommandConfig'],
                    CliNoInteractionConfigContract::class          => [CliServerServiceProvider::class, 'publishNoInteractionConfig'],
                    CliQuietInteractionConfigContract::class       => [CliServerServiceProvider::class, 'publishQuietInteractionConfig'],
                    CliSilentInteractionConfigContract::class      => [CliServerServiceProvider::class, 'publishSilentInteractionConfig'],
                    ListBashCommand::class                         => [CliServerServiceProvider::class, 'publishListBashCommand'],
                    ListCommand::class                             => [CliServerServiceProvider::class, 'publishListCommand'],
                    VersionCommand::class                          => [CliServerServiceProvider::class, 'publishVersionCommand'],
                    CheckForHelpOptionsMiddleware::class           => [CliServerServiceProvider::class, 'publishCheckForHelpOptionsMiddleware'],
                    CheckForVersionOptionsMiddleware::class        => [CliServerServiceProvider::class, 'publishCheckForVersionOptionsMiddleware'],
                    CheckGlobalInteractionOptionsMiddleware::class => [CliServerServiceProvider::class, 'publishCheckGlobalInteractionOptionsMiddleware'],
                ]
            )
        );

        $router = new Router(
            container: $container,
            collection: $collection,
            outputFactory: $outputFactory
        );

        $input = InputFactory::fromGlobals($argv, 'cli', 'list');

        if ($withInputMiddleware) {
            self::assertContains(CheckForVersionOptionsMiddleware::class, $config->inputReceivedMiddleware);

            $inputAfterMiddleware = new InputReceivedHandler($container, ...$config->inputReceivedMiddleware)
                ->inputReceived($input);

            self::assertInstanceOf(InputContract::class, $inputAfterMiddleware);

            $input = $inputAfterMiddleware;
        }

        return $router->dispatch($input);
    }

    /**
     * Join every message of an output into one string.
     */
    private function text(OutputContract $output): string
    {
        return implode(
            '',
            array_map(
                static fn (MessageContract $message): string => $message->getText(),
                $output->getMessages()
            )
        );
    }
}
