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

use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Cli\Interaction\Message\Contract\MessageContract;
use Valkyrja\Cli\Interaction\Message\Header;
use Valkyrja\Cli\Interaction\Message\Message;
use Valkyrja\Cli\Interaction\Message\NewLine;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Attribute\OptionParameter;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Handler;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Provider\CliRoutingCliRouteProvider;
use Valkyrja\Cli\Server\Constant\CommandName;

use const PHP_VERSION;

class VersionCommand
{
    public function __construct(
        protected OutputFactoryContract $outputFactory,
        protected CliConfigContract $config,
        protected RouteContract $route,
    ) {
    }

    /**
     * The help text.
     */
    public static function help(): MessageContract
    {
        return new Message('A command to show the application version and info.');
    }

    #[Route(
        name: CommandName::VERSION,
        description: 'Get the application version',
        helpText: [self::class, 'help'],
    )]
    #[OptionParameter(
        name: 'short',
        description: 'Output the version number only',
        shortNames: ['s'],
    )]
    #[OptionParameter(
        name: 'plain',
        description: 'Output version info without the banner',
        shortNames: ['p'],
    )]
    #[Handler([CliRoutingCliRouteProvider::class, 'versionHandler'])]
    public function run(): OutputContract
    {
        /** @var string $namespace */
        $namespace = $this->config->namespace;
        /** @var string $version */
        $version = $this->config->version;

        if ($this->route->hasProvidedOption('short')) {
            return $this->outputFactory
                ->createOutput()
                ->withMessages(new Message($version));
        }

        if ($this->route->hasProvidedOption('plain')) {
            return $this->outputFactory
                ->createOutput()
                ->withMessages(
                    new Message($namespace . ' v' . $version),
                    new NewLine(),
                    new Message('Built on Valkyrja v' . ApplicationInfo::VERSION . ' (date: ' . ApplicationInfo::VERSION_BUILD_DATE_TIME . ')'),
                    new NewLine(),
                    new Message('Running on PHP ' . PHP_VERSION),
                );
        }

        return $this->outputFactory
            ->createOutput()
            ->withMessages(
                new Header($namespace, $version, $this->route),
            );
    }
}
