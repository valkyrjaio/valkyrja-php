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
use Valkyrja\Cli\Routing\Attribute\ArgumentParameter;
use Valkyrja\Cli\Routing\Attribute\OptionParameter;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Handler;
use Valkyrja\Cli\Routing\Attribute\Route\Middleware;
use Valkyrja\Cli\Routing\Attribute\Route\Name;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Enum\ArgumentMode;
use Valkyrja\Cli\Routing\Enum\ArgumentValueMode;
use Valkyrja\Cli\Routing\Enum\OptionMode;
use Valkyrja\Cli\Routing\Enum\OptionValueMode;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Tests\Fixtures\Cli\Middleware\ProcessExitingMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Type\Data\Cast;
use Valkyrja\Type\Enum\CastType;

/**
 * Command class to test commands.
 */
#[Name('className')]
final class CommandWithAllAttributesFixture
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
    #[Name('actionName')]
    #[OptionParameter(
        name: 'optionName',
        description: 'The option for the command',
        valueDisplayName: 'name',
        cast: new Cast(CastType::string),
        defaultValue: 'foo',
        shortNames: ['o'],
        validValues: ['foo', 'bar'],
        mode: OptionMode::REQUIRED,
        valueMode: OptionValueMode::ARRAY,
    )]
    #[ArgumentParameter(
        name: 'argumentName',
        description: 'The argument for the command',
        cast: new Cast(CastType::string),
        mode: ArgumentMode::REQUIRED,
        valueMode: ArgumentValueMode::ARRAY,
    )]
    #[Middleware(RouteDispatchedMiddlewareFixture::class)]
    #[Middleware(RouteMatchedMiddlewareFixture::class)]
    #[Middleware(ThrowableCaughtMiddlewareFixture::class)]
    #[Middleware(ProcessExitingMiddlewareFixture::class)]
    public function run(OutputFactoryContract $outputFactory): OutputContract
    {
        return $outputFactory->createOutput()->withMessages(new Message(self::NAME));
    }
}
