<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Cli\Routing\Command;

use Valkyrja\Cli\Interaction\Message\Contract\MessageContract;
use Valkyrja\Cli\Interaction\Message\Message;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Handler;
use Valkyrja\Cli\Routing\Attribute\Route\Middleware;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Tests\Fixtures\Cli\Middleware\AllMiddlewareFixture;

final class CommandWithAllMiddlewareFixture
{
    /** @var non-empty-string */
    public const string NAME = 'test2';
    /** @var non-empty-string */
    public const string DESCRIPTION = 'A test2 command';
    /** @var non-empty-string */
    public const string HELP_TEXT = 'A test2 command';

    /**
     * The help text.
     */
    public static function help(): MessageContract
    {
        return new Message(self::HELP_TEXT);
    }

    /**
     * Handler for the command.
     */
    public static function handler(ContainerContract $container, RouteContract $route): OutputContract
    {
        $controller = new self();

        return $controller->run(
            $container->getSingleton(OutputFactoryContract::class)
        );
    }

    #[Route(
        name: self::NAME,
        description: self::DESCRIPTION,
        helpText: [self::class, 'help'],
    )]
    #[Handler([self::class, 'handler'])]
    #[Middleware(AllMiddlewareFixture::class)]
    public function run(OutputFactoryContract $outputFactory): OutputContract
    {
        return $outputFactory->createOutput()->withMessages(new Message(self::NAME));
    }
}
