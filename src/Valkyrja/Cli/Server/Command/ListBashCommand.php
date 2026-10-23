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

use Valkyrja\Cli\Interaction\Message\Contract\MessageContract;
use Valkyrja\Cli\Interaction\Message\Message;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Handler;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Data\ArgumentParameter;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Provider\CliRoutingCliRouteProvider;
use Valkyrja\Cli\Server\Constant\CommandName;

use function array_filter;
use function array_map;
use function implode;
use function str_starts_with;
use function strpos;
use function substr;

class ListBashCommand
{
    public function __construct(
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
        return new Message('A command to list all the commands present within the Cli component for bash completion.');
    }

    #[Route(
        name: CommandName::LIST_BASH,
        description: 'List all commands for bash completion',
        helpText: [self::class, 'help'],
        arguments: [
            new ArgumentParameter(
                name: 'applicationName',
                description: 'The application name',
            ),
            new ArgumentParameter(
                name: 'namespace',
                description: 'An optional namespace to filter commands by',
            ),
        ]
    )]
    #[Handler([CliRoutingCliRouteProvider::class, 'listBashHandler'])]
    public function run(): OutputContract
    {
        $output = $this->outputFactory
            ->createOutput();

        $routes    = $this->collection->all();
        $namespace = $this->route->getArgumentValue('namespace');
        $colonAt   = false;

        if ($namespace !== '') {
            $colonAt = strpos($namespace, ':');

            $routes = array_filter($routes, static fn (RouteContract $route) => str_starts_with($route->getName(), $namespace));
        }

        $routesForBash = array_map(
            static fn (RouteContract $route): string => $colonAt !== false ? substr($route->getName(), $colonAt + 1) : $route->getName(),
            $routes
        );

        /** @var non-empty-string $routesForBashString */
        $routesForBashString = implode(' ', $routesForBash);

        /** @psalm-suppress ArgumentTypeCoercion */
        return $output
            ->withAddedMessages(
                new Message($routesForBashString)
            );
    }
}
