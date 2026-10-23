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
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Enum\ArgumentMode;
use Valkyrja\Cli\Routing\Enum\ArgumentValueMode;
use Valkyrja\Cli\Routing\Enum\OptionMode;
use Valkyrja\Cli\Routing\Enum\OptionValueMode;
use Valkyrja\Container\Manager\Contract\ContainerContract;

/**
 * Exercises the CLI argument and option permutations that CommandWithAllAttributesFixture leaves uncovered.
 */
final class CommandWithParameterPermutationsFixture
{
    /** @var non-empty-string */
    public const string NAME = 'permutations';
    /** @var non-empty-string */
    public const string DESCRIPTION = 'A permutations command';
    /** @var non-empty-string */
    public const string HELP_TEXT = 'A permutations command';

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
        return new self()->run(
            $container->getSingleton(OutputFactoryContract::class)
        );
    }

    #[Route(
        name: self::NAME,
        description: self::DESCRIPTION,
        helpText: [self::class, 'help'],
    )]
    #[Handler([self::class, 'handler'])]
    #[ArgumentParameter(
        name: 'optionalArgument',
        description: 'An optional single-value argument',
        mode: ArgumentMode::OPTIONAL,
        valueMode: ArgumentValueMode::DEFAULT,
    )]
    #[OptionParameter(
        name: 'optionalOption',
        description: 'An optional single-value long-only option',
        mode: OptionMode::OPTIONAL,
        valueMode: OptionValueMode::DEFAULT,
    )]
    #[OptionParameter(
        name: 'flag',
        description: 'A valueless flag option',
        mode: OptionMode::OPTIONAL,
        valueMode: OptionValueMode::NONE,
    )]
    public function run(OutputFactoryContract $outputFactory): OutputContract
    {
        return $outputFactory->createOutput()->withMessages(new Message(self::NAME));
    }
}
