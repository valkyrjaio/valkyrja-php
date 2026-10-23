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
use Valkyrja\Cli\Interaction\Message\Messages;
use Valkyrja\Cli\Interaction\Message\NewLine;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Handler;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Data\Contract\ArgumentParameterContract;
use Valkyrja\Cli\Routing\Data\Contract\OptionParameterContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Data\Option\HelpOptionParameter;
use Valkyrja\Cli\Routing\Data\Option\NoInteractionOptionParameter;
use Valkyrja\Cli\Routing\Data\Option\QuietOptionParameter;
use Valkyrja\Cli\Routing\Data\Option\SilentOptionParameter;
use Valkyrja\Cli\Routing\Data\Option\VersionOptionParameter;
use Valkyrja\Cli\Routing\Data\OptionParameter;
use Valkyrja\Cli\Routing\Enum\ArgumentValueMode;
use Valkyrja\Cli\Routing\Enum\OptionMode;
use Valkyrja\Cli\Routing\Enum\OptionValueMode;
use Valkyrja\Cli\Routing\Provider\CliRoutingCliRouteProvider;
use Valkyrja\Cli\Server\Constant\CommandName;

use function implode;
use function wordwrap;

class HelpCommand
{
    protected RouteContract $helpRoute;

    public function __construct(
        protected CliConfigContract $config,
        protected RouteContract $route,
        protected RouteCollectionContract $collection,
        protected OutputFactoryContract $outputFactory,
    ) {
    }

    /**
     * The help text.
     */
    public static function help(): MessageContract
    {
        return new Message('A command to get help for a specific command.');
    }

    #[Route(
        name: CommandName::HELP,
        description: 'Help for a command',
        helpText: [self::class, 'help'],
        options: [
            new OptionParameter(
                name: 'command',
                description: 'The name of the command to get help for',
                valueDisplayName: 'command',
                mode: OptionMode::REQUIRED
            ),
        ]
    )]
    #[Handler([CliRoutingCliRouteProvider::class, 'helpHandler'])]
    public function run(): OutputContract
    {
        $commandName = $this->route->getOptionValue('command');

        if (! $this->collection->has($commandName)) {
            return $this->outputFactory
                ->createOutput()
                ->withExitCode(ExitCode::ERROR)
                ->withAddedMessages(
                    new Banner(new ErrorMessage("Command `$commandName` was not found."))
                );
        }

        $this->helpRoute = $this->collection->get($commandName);

        /** @var string $namespace */
        $namespace = $this->config->namespace;
        /** @var string $version */
        $version = $this->config->version;

        $output = $this->outputFactory
            ->createOutput()
            ->withMessages(new Header($namespace, $version, $this->route));

        return $this->getHelpText($output);
    }

    /**
     * Get the help text for a given command.
     */
    protected function getHelpText(OutputContract $output): OutputContract
    {
        $route            = $this->helpRoute;
        $argumentMessages = $this->getArgumentsMessages();
        $optionMessages   = $this->getOptionsMessages();

        $output = $output
            ->withAddedMessages(
                new NewLine(),
                $this->getNameMessages(),
                new NewLine(),
                new NewLine(),
                $this->getDescriptionMessages(),
                new NewLine(),
                new NewLine(),
                $this->getUsageMessages(),
                new NewLine(),
                new NewLine(),
                ...$argumentMessages,
                ...$optionMessages,
            );

        if ($route->hasHelpText()) {
            $helpText = $route->getHelpTextMessage();

            return $output->withAddedMessages(
                $this->getHelpTextMessages($helpText),
                new NewLine(),
            );
        }

        return $output;
    }

    /**
     * Get the options messages.
     *
     * @return array<int, MessageContract>
     */
    protected function getOptionsMessages(): array
    {
        $route          = $this->helpRoute;
        $optionMessages = [];

        if ($route->hasOptions()) {
            $optionMessages[] = $this->getOptionsHeadingMessages();
            $optionMessages[] = new NewLine();

            foreach ($route->getOptions() as $option) {
                $optionMessages[] = $this->getOptionMessages($option);
            }
        }

        $optionMessages[] = $this->getGlobalOptionsHeadingMessages();
        $optionMessages[] = new NewLine();

        $optionMessages[] = $this->getOptionMessages(new QuietOptionParameter());
        $optionMessages[] = $this->getOptionMessages(new SilentOptionParameter());
        $optionMessages[] = $this->getOptionMessages(new NoInteractionOptionParameter());
        $optionMessages[] = $this->getOptionMessages(new HelpOptionParameter());
        $optionMessages[] = $this->getOptionMessages(new VersionOptionParameter());

        return $optionMessages;
    }

    /**
     * Get the arguments messages.
     *
     * @return array<int, MessageContract>
     */
    protected function getArgumentsMessages(): array
    {
        $route            = $this->helpRoute;
        $argumentMessages = [];

        if ($route->hasArguments()) {
            $argumentMessages[] = $this->getArgumentsHeadingMessages();
            $argumentMessages[] = new NewLine();

            foreach ($route->getArguments() as $argument) {
                $argumentMessages[] = $this->getArgumentMessages($argument);
            }
        }

        return $argumentMessages;
    }

    /**
     * Get name messages.
     */
    protected function getNameMessages(): Messages
    {
        return new Messages(
            new Message('Name: ', new HighlightedTextFormatter()),
            new Message($this->helpRoute->getName()),
        );
    }

    /**
     * Get description messages.
     */
    protected function getDescriptionMessages(): Messages
    {
        return new Messages(
            new Message('Description:', new HighlightedTextFormatter()),
            new NewLine(),
            $this->getIndentedText(new Message($this->helpRoute->getDescription())),
        );
    }

    /**
     * Get help text messages.
     */
    protected function getHelpTextMessages(MessageContract $helpText): Messages
    {
        return new Messages(
            new Message('Help:', new HighlightedTextFormatter()),
            new NewLine(),
            $this->getIndentedText($helpText),
            new NewLine(),
        );
    }

    /**
     * Get usage messages.
     */
    protected function getUsageMessages(): Messages
    {
        $route = $this->helpRoute;
        $usage = $route->getName();

        if ($route->hasOptions()) {
            $usage .= ' [options]';
        }

        $usage .= ' [global options]';

        if ($route->hasArguments()) {
            foreach ($route->getArguments() as $argument) {
                $usage .= ' ['
                    . $argument->getName()
                    . ($argument->getValueMode() === ArgumentValueMode::ARRAY ? '...' : '')
                    . ']';
            }
        }

        return new Messages(
            new Message('Usage:', new HighlightedTextFormatter()),
            new NewLine(),
            $this->getIndentedText(new Message($usage)),
        );
    }

    /**
     * Get options heading messages.
     */
    protected function getOptionsHeadingMessages(): Messages
    {
        return new Messages(
            new Message('Options:', new HighlightedTextFormatter()),
        );
    }

    /**
     * Get global options heading messages.
     */
    protected function getGlobalOptionsHeadingMessages(): Messages
    {
        return new Messages(
            new Message('Global Options:', new HighlightedTextFormatter()),
        );
    }

    /**
     * Get option messages.
     */
    protected function getOptionMessages(OptionParameterContract $option): Messages
    {
        $optionMessages = [];

        $optionMessages[] = new Message('  ');
        $optionMessages[] = new Message('--' . $option->getName(), new Formatter(new TextColorFormat(TextColor::MAGENTA)));

        $this->addShortNamesOptionMessages($optionMessages, $option);
        $this->addValueDisplayNameOptionMessages($optionMessages, $option);

        $optionMessages[] = new NewLine();
        $optionMessages[] = new Message('    ');
        $optionMessages[] = new Message($option->getDescription());

        $this->addValidValuesOptionMessages($optionMessages, $option);

        $optionMessages[] = new NewLine();
        $optionMessages[] = new NewLine();

        return new Messages(
            ...$optionMessages
        );
    }

    /**
     * Add short names option messages.
     *
     * @param MessageContract[] $messages The messages
     */
    protected function addShortNamesOptionMessages(array &$messages, OptionParameterContract $option): void
    {
        $shortNames = $option->getShortNames();

        if ($shortNames !== []) {
            $messages[] = new Message(', ');
            $messages[] = new Message('-' . implode('|', $shortNames), new Formatter(new TextColorFormat(TextColor::MAGENTA)));
        }
    }

    /**
     * Add value display name option messages.
     *
     * @param MessageContract[] $messages The messages
     */
    protected function addValueDisplayNameOptionMessages(array &$messages, OptionParameterContract $option): void
    {
        if ($option->hasValueDisplayName()) {
            $valueDisplayName = $option->getValueDisplayName();

            $messages[] = new Message(' ');

            $text = '';

            if ($option->getValueMode() === OptionValueMode::ARRAY) {
                $text = '...';
            }

            if ($option->getMode() === OptionMode::REQUIRED) {
                $text .= '=' . $valueDisplayName;
            } else {
                $text .= '[=' . $valueDisplayName . ']';
            }

            $messages[] = new Message($text, new HighlightedTextFormatter());
        }
    }

    /**
     * Add valid values option messages.
     *
     * @param MessageContract[] $messages The messages
     */
    protected function addValidValuesOptionMessages(array &$messages, OptionParameterContract $option): void
    {
        $validValues = $option->getValidValues();

        if ($validValues !== []) {
            $defaultValue = $option->getDefaultValue();

            $valueSpacing = "\n      - ";

            $messages[] = new NewLine();
            $messages[] = new NewLine();
            $messages[] = new Message('    ');
            $messages[] = new Message('Valid values:');

            foreach ($validValues as $validValue) {
                $messages[] = new Message($valueSpacing . "`$validValue`");

                if ($validValue === $defaultValue) {
                    $messages[] = new Message(' (default)', new HighlightedTextFormatter());
                }
            }
        }
    }

    /**
     * Get arguments heading messages.
     */
    protected function getArgumentsHeadingMessages(): Messages
    {
        return new Messages(
            new Message('Arguments:', new HighlightedTextFormatter()),
        );
    }

    /**
     * Get argument messages.
     */
    protected function getArgumentMessages(ArgumentParameterContract $argument): Messages
    {
        $argumentMessages = [];

        $argumentMessages[] = new Message('  ');
        $argumentMessages[] = new Message($argument->getName());
        $argumentMessages[] = new NewLine();
        $argumentMessages[] = new Message('    ');
        $argumentMessages[] = new Message($argument->getDescription());
        $argumentMessages[] = new NewLine();
        $argumentMessages[] = new NewLine();

        return new Messages(
            ...$argumentMessages
        );
    }

    /**
     * Get indented text.
     */
    protected function getIndentedText(MessageContract $message): MessageContract
    {
        $spaces = '  ';

        /** @var non-empty-string $wrappedText */
        $wrappedText = wordwrap(
            $spaces . $message->getText(),
            100,
            "\n$spaces"
        );

        return $message->withText(text: $wrappedText);
    }
}
