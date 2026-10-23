<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Cli\Command;

use Valkyrja\Cli\Interaction\Enum\ExitCode;
use Valkyrja\Cli\Interaction\Enum\TextColor;
use Valkyrja\Cli\Interaction\Format\TextColorFormat;
use Valkyrja\Cli\Interaction\Formatter\Formatter;
use Valkyrja\Cli\Interaction\Formatter\HighlightedTextFormatter;
use Valkyrja\Cli\Interaction\Message\Banner;
use Valkyrja\Cli\Interaction\Message\Contract\MessageContract;
use Valkyrja\Cli\Interaction\Message\ErrorMessage;
use Valkyrja\Cli\Interaction\Message\Message;
use Valkyrja\Cli\Interaction\Message\NewLine;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Handler;
use Valkyrja\Cli\Server\Command\VersionCommand;
use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Routing\Cli\Command\Constant\CommandName;
use Valkyrja\Http\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Http\Routing\Data\Contract\DynamicRouteContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Provider\HttpRoutingCliRouteProvider;

use function usort;

class ListCommand
{
    /**
     * The help text.
     */
    public static function help(): MessageContract
    {
        return new Message('A command to list all the routes present within the Http component.');
    }

    #[Route(
        name: CommandName::LIST,
        description: 'List all routes',
        helpText: [self::class, 'help'],
    )]
    #[Handler([HttpRoutingCliRouteProvider::class, 'listHandler'])]
    public function run(VersionCommand $version, RouteCollectionContract $collection, OutputFactoryContract $outputFactory): OutputContract
    {
        $output = $outputFactory
            ->createOutput();

        $routes = $collection->getAll(RequestMethod::ANY);

        if ($routes === []) {
            return $output
                ->withExitCode(ExitCode::ERROR)
                ->withAddedMessages(
                    new Banner(new ErrorMessage('No routes were found'))
                );
        }

        usort($routes, static fn (RouteContract $a, RouteContract $b): int => $a->getPath() <=> $b->getPath());

        $output = $version
            ->run()
            ->withAddedMessages(
                new NewLine(),
                new Message('Routes:', new HighlightedTextFormatter()),
                new NewLine()
            );

        foreach ($routes as $route) {
            $output = $output->withAddedMessages(
                new Message('  '),
                new Message($route->getPath(), new Formatter(new TextColorFormat(TextColor::MAGENTA))),
                new NewLine(),
                new Message('    - '),
                new Message('Name: '),
                new Message($route->getName(), new HighlightedTextFormatter()),
                new NewLine(),
            );

            $output = $this->getOutputForDynamicRoute($output, $route);
        }

        return $output->withAddedMessages(new NewLine());
    }

    /**
     * Update an output with dynamic route information.
     */
    protected function getOutputForDynamicRoute(OutputContract $output, RouteContract $route): OutputContract
    {
        if ($route instanceof DynamicRouteContract) {
            $regex = $route->getRegex();

            if ($regex !== '') {
                $output = $output->withAddedMessages(
                    new Message('    - '),
                    new Message('Regex: '),
                    new Message($regex, new HighlightedTextFormatter()),
                    new NewLine(),
                );
            }
        }

        return $output;
    }
}
