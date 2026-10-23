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
 * Exercises a full matrix of CLI argument and option modes so attribute construction can be asserted.
 */
final class CommandWithParameterCombinationsFixture
{
    /** @var non-empty-string */
    public const string NAME = 'combinations';
    /** @var non-empty-string */
    public const string DESCRIPTION = 'A combinations command';
    /** @var non-empty-string */
    public const string HELP_TEXT = 'A combinations command';

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
        name: 'required',
        description: 'A required single-value argument',
        mode: ArgumentMode::REQUIRED,
        valueMode: ArgumentValueMode::DEFAULT,
    )]
    #[ArgumentParameter(
        name: 'rest',
        description: 'An optional array argument',
        mode: ArgumentMode::OPTIONAL,
        valueMode: ArgumentValueMode::ARRAY,
    )]
    #[OptionParameter(
        name: 'format',
        description: 'A required single-value option',
        valueDisplayName: 'fmt',
        defaultValue: 'json',
        shortNames: ['f'],
        validValues: ['json', 'xml'],
        mode: OptionMode::REQUIRED,
        valueMode: OptionValueMode::DEFAULT,
    )]
    #[OptionParameter(
        name: 'flag',
        description: 'A valueless flag option',
        mode: OptionMode::OPTIONAL,
        valueMode: OptionValueMode::NONE,
    )]
    #[OptionParameter(
        name: 'tags',
        description: 'A repeatable array option',
        valueMode: OptionValueMode::ARRAY,
    )]
    public function run(OutputFactoryContract $outputFactory): OutputContract
    {
        return $outputFactory->createOutput()->withMessages(new Message(self::NAME));
    }
}
