<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Server\Command;

use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Cli\Interaction\Enum\ExitCode;
use Valkyrja\Cli\Interaction\Enum\TextColor;
use Valkyrja\Cli\Interaction\Format\TextColorFormat;
use Valkyrja\Cli\Interaction\Formatter\Formatter;
use Valkyrja\Cli\Interaction\Formatter\HighlightedTextFormatter;
use Valkyrja\Cli\Interaction\Message\Banner;
use Valkyrja\Cli\Interaction\Message\Contract\MessageContract;
use Valkyrja\Cli\Interaction\Message\ErrorMessage;
use Valkyrja\Cli\Interaction\Message\Header;
use Valkyrja\Cli\Interaction\Message\Message;
use Valkyrja\Cli\Interaction\Message\NewLine;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Attribute\OptionParameter;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Handler;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Provider\CliRoutingCliRouteProvider;
use Valkyrja\Cli\Server\Constant\CommandName;

use function array_filter;
use function str_starts_with;
use function usort;

class ListCommand
{
    public function __construct(
        protected CliConfigContract $config,
        protected RouteContract $route,
        protected RouteCollectionContract $collection,
        protected OutputFactoryContract $outputFactory
    ) {
    }

    /**
     * The help text.
     */
    public static function help(): MessageContract
    {
        return new Message('A command to list all the commands present within the Cli component.');
    }

    #[Route(
        name: CommandName::LIST,
        description: 'List all commands',
        helpText: [self::class, 'help']
    )]
    #[OptionParameter(
        name: 'namespace',
        description: 'An optional namespace to filter commands by',
        valueDisplayName: 'namespace',
        shortNames: ['n']
    )]
    #[Handler([CliRoutingCliRouteProvider::class, 'listHandler'])]
    public function run(): OutputContract
    {
        $routes    = $this->collection->all();
        $namespace = $this->route->getOptionValue('namespace');

        if ($namespace !== '') {
            $routes = $this->filterRoutesByNamespace($routes, $namespace);
        }

        if ($routes === []) {
            return $this->getNoRoutesErrorOutput($namespace);
        }

        $this->sortRoutes($routes);

        /** @var string $appNamespace */
        $appNamespace = $this->config->namespace;
        /** @var string $appVersion */
        $appVersion = $this->config->version;

        $output = $this->outputFactory
            ->createOutput()
            ->withMessages(new Header($appNamespace, $appVersion, $this->route));

        $output = $this->addHeaderMessages($output, $namespace);
        $output = $this->addRoutesMessages($output, $routes);

        return $output->withAddedMessages(new NewLine());
    }

    protected function getNoRoutesErrorOutput(string $namespace): OutputContract
    {
        $errorMessage = 'No routes found.';

        if ($namespace !== '') {
            $errorMessage = "Namespace `$namespace` was not found.";
        }

        return $this->outputFactory
            ->createOutput()
            ->withExitCode(ExitCode::ERROR)
            ->withAddedMessages(
                new Banner(new ErrorMessage($errorMessage))
            );
    }

    /**
     * Filter a list of routes by namespace.
     *
     * @param RouteContract[] $routes The routes
     *
     * @return RouteContract[]
     */
    protected function filterRoutesByNamespace(array $routes, string $namespace): array
    {
        return array_filter($routes, static fn (RouteContract $route) => str_starts_with($route->getName(), $namespace));
    }

    /**
     * Sort the list of routes.
     *
     * @param RouteContract[] $routes The routes
     */
    protected function sortRoutes(array &$routes): void
    {
        usort($routes, static fn (RouteContract $a, RouteContract $b): int => $a->getName() <=> $b->getName());
    }

    /**
     * Add the header to the output.
     */
    protected function addHeaderMessages(OutputContract $output, string $namespace): OutputContract
    {
        return $output->withAddedMessages(
            new Message('Commands' . ($namespace !== '' ? " [$namespace]:" : ':'), new HighlightedTextFormatter()),
            new NewLine()
        );
    }

    /**
     * Add the routes to the output.
     *
     * @param RouteContract[] $routes The routes
     */
    protected function addRoutesMessages(OutputContract $output, array $routes): OutputContract
    {
        foreach ($routes as $route) {
            $output = $this->addRouteMessages($output, $route);
        }

        return $output;
    }

    /**
     * Add a route to the output.
     */
    protected function addRouteMessages(OutputContract $output, RouteContract $route): OutputContract
    {
        return $output->withAddedMessages(
            new Message('  '),
            new Message($route->getName(), new Formatter(new TextColorFormat(TextColor::MAGENTA))),
            new NewLine(),
            new Message('    - '),
            new Message($route->getDescription(), new HighlightedTextFormatter()),
            new NewLine(),
        );
    }
}
