<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry;

use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Application\Entry\Abstract\App;
use Valkyrja\Cli\Interaction\Input\Contract\InputContract;
use Valkyrja\Cli\Interaction\Input\Factory\InputFactory;
use Valkyrja\Cli\Server\Handler\Contract\InputHandlerContract;

class Cli extends App
{
    /**
     * Run the cli app.
     */
    public static function run(CliConfigContract $config): void
    {
        $app = static::start(
            config: $config,
        );

        $container = $app->getContainer();

        self::bootstrapThrowableHandler($app, $container);

        $handler = $container->getSingleton(InputHandlerContract::class);
        $input   = static::getInput(config: $config);
        $handler->run($input);
    }

    /**
     * Get the input.
     */
    public static function getInput(CliConfigContract $config): InputContract
    {
        /** @var non-empty-string[] $args */
        $args = $_SERVER['argv'] ?? [];

        /** @var non-empty-string $applicationName */
        $applicationName = $config->applicationName;

        $input = InputFactory::fromGlobals(
            args: $args,
            applicationName: $applicationName,
            commandName: $config->defaultCommandName,
        );

        return $input;
    }
}
