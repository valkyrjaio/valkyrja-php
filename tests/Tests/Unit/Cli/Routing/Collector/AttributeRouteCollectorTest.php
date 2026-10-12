<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cli\Routing\Collector;

use ReflectionException;
use Valkyrja\Attribute\Collector\Collector;
use Valkyrja\Cli\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Cli\Routing\Collector\AttributeRouteCollector;
use Valkyrja\Cli\Routing\Data\ArgumentParameter;
use Valkyrja\Cli\Routing\Data\OptionParameter;
use Valkyrja\Cli\Routing\Data\Route;
use Valkyrja\Cli\Routing\Enum\ArgumentMode;
use Valkyrja\Cli\Routing\Enum\ArgumentValueMode;
use Valkyrja\Cli\Routing\Enum\OptionMode;
use Valkyrja\Cli\Routing\Enum\OptionValueMode;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingNoRouteHandlerException;
use Valkyrja\Reflection\Reflector\Reflector;
use Valkyrja\Tests\Fixtures\Cli\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Middleware\ProcessExitingMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Routing\Command\CommandFixture;
use Valkyrja\Tests\Fixtures\Cli\Routing\Command\CommandMissingHandlerFixture;
use Valkyrja\Tests\Fixtures\Cli\Routing\Command\CommandWithAllAttributesFixture;
use Valkyrja\Tests\Fixtures\Cli\Routing\Command\CommandWithAllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Cli\Routing\Command\CommandWithParameterCombinationsFixture;
use Valkyrja\Tests\Fixtures\Cli\Routing\Command\CommandWithParameterPermutationsFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Type\Enum\CastType;

/**
 * Test the AttributeCollector class.
 */
final class AttributeRouteCollectorTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testDefaults(): void
    {
        $collector = new AttributeRouteCollector(
            attributes: new Collector(),
            reflection: new Reflector()
        );

        self::assertEmpty($collector->getRoutes(self::class));
    }

    public function testGetCommands(): void
    {
        $collector = new AttributeRouteCollector(
            attributes: new Collector(),
            reflection: new Reflector()
        );

        $commands = $collector->getRoutes(CommandFixture::class);

        self::assertNotEmpty($commands);
        self::assertCount(1, $commands);
        self::assertInstanceOf(Route::class, $command = $commands[0]);
        self::assertSame(CommandFixture::NAME, $command->getName());
        self::assertSame(CommandFixture::DESCRIPTION, $command->getDescription());
        self::assertSame(CommandFixture::HELP_TEXT, $command->getHelpTextMessage()->getText());
        self::assertSame([CommandFixture::class, 'handler'], $command->getHandler());
        self::assertNotEmpty($command->getOptions());
        self::assertInstanceOf(OptionParameter::class, $option = $command->getOptions()[0]);
        self::assertFalse($option->hasCast());
        self::assertNotEmpty($command->getArguments());
        self::assertInstanceOf(ArgumentParameter::class, $argument = $command->getArguments()[0]);
        self::assertFalse($argument->hasCast());
    }

    public function testRejectsARouteThatNamesNoHandler(): void
    {
        $collector = new AttributeRouteCollector(
            attributes: new Collector(),
            reflection: new Reflector()
        );

        // Without this the route would keep the stand-in and fail only once
        // the command was invoked, long after the author could act on it
        $this->expectException(CliRoutingNoRouteHandlerException::class);
        $this->expectExceptionMessage(
            'Route `' . CommandMissingHandlerFixture::NAME . '` on '
            . CommandMissingHandlerFixture::class . '::run names no handler'
        );

        $collector->getRoutes(CommandMissingHandlerFixture::class);
    }

    public function testKeepsAHandlerTheRouteAttributeNamesItself(): void
    {
        $attributes = new Collector();
        $collector  = new AttributeRouteCollector(
            attributes: $attributes,
            reflection: new Reflector()
        );

        // Were this fixture to gain a RouteHandler anywhere, the rejection
        // above would pass against a guard that tests the attribute instead
        self::assertSame([], $attributes->forClassMembers(CommandFixture::class, RouteHandler::class));

        $commands = $collector->getRoutes(CommandFixture::class);

        self::assertSame([CommandFixture::class, 'handler'], $commands[0]->getHandler());
    }

    public function testGetCommandsWithMoreAttributes(): void
    {
        $collector = new AttributeRouteCollector(
            attributes: new Collector(),
            reflection: new Reflector()
        );

        $commands = $collector->getRoutes(CommandWithAllAttributesFixture::class);

        self::assertNotEmpty($commands);
        self::assertCount(1, $commands);
        self::assertInstanceOf(Route::class, $command = $commands[0]);
        self::assertSame('className.test2.actionName', $command->getName());
        self::assertSame(CommandWithAllAttributesFixture::DESCRIPTION, $command->getDescription());
        self::assertSame(CommandWithAllAttributesFixture::HELP_TEXT, $command->getHelpTextMessage()->getText());
        self::assertSame([CommandWithAllAttributesFixture::class, 'handler'], $command->getHandler());
        self::assertNotEmpty($command->getOptions());
        self::assertInstanceOf(OptionParameter::class, $option = $command->getOptions()[0]);
        self::assertNotEmpty($command->getArguments());
        self::assertSame('optionName', $option->getName());
        self::assertSame('The option for the command', $option->getDescription());
        self::assertSame(CastType::string->value, $option->getCast()->type);
        self::assertSame('name', $option->getValueDisplayName());
        self::assertSame('foo', $option->getDefaultValue());
        self::assertSame(['o'], $option->getShortNames());
        self::assertSame(['foo', 'bar'], $option->getValidValues());
        self::assertSame(OptionMode::REQUIRED, $option->getMode());
        self::assertSame(OptionValueMode::ARRAY, $option->getValueMode());
        self::assertInstanceOf(ArgumentParameter::class, $argument = $command->getArguments()[0]);
        self::assertSame('argumentName', $argument->getName());
        self::assertSame('The argument for the command', $argument->getDescription());
        self::assertSame(ArgumentMode::REQUIRED, $argument->getMode());
        self::assertSame(ArgumentValueMode::ARRAY, $argument->getValueMode());
        self::assertSame(CastType::string->value, $argument->getCast()->type);
        self::assertSame([RouteDispatchedMiddlewareFixture::class], $command->getRouteDispatchedMiddleware());
        self::assertSame([RouteMatchedMiddlewareFixture::class], $command->getRouteMatchedMiddleware());
        self::assertSame([ThrowableCaughtMiddlewareFixture::class], $command->getThrowableCaughtMiddleware());
        self::assertSame([ProcessExitingMiddlewareFixture::class], $command->getProcessExitingMiddleware());
    }

    public function testGetRoutesWithSingleMiddlewareThatHasAllTypes(): void
    {
        $collector = new AttributeRouteCollector(
            attributes: new Collector(),
            reflection: new Reflector()
        );
        $routes = $collector->getRoutes(CommandWithAllMiddlewareFixture::class);

        self::assertCount(1, $routes);

        $route = $routes[0];

        self::assertSame(CommandWithAllMiddlewareFixture::NAME, $route->getName());
        self::assertSame(CommandWithAllMiddlewareFixture::DESCRIPTION, $route->getDescription());
        self::assertSame(CommandWithAllMiddlewareFixture::HELP_TEXT, $route->getHelpTextMessage()->getText());
        self::assertSame([AllMiddlewareFixture::class], $route->getRouteDispatchedMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getRouteMatchedMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getProcessExitingMiddleware());
        self::assertSame([AllMiddlewareFixture::class], $route->getThrowableCaughtMiddleware());
    }

    /**
     * The attribute path converts the complementary argument/option permutations
     * (optional single-value argument, optional long-only option, and a NONE flag) into
     * data-class parameters with the expected modes and value modes.
     *
     * @throws ReflectionException
     */
    public function testGetCommandsWithParameterPermutations(): void
    {
        $collector = new AttributeRouteCollector(
            attributes: new Collector(),
            reflection: new Reflector()
        );

        $commands = $collector->getRoutes(CommandWithParameterPermutationsFixture::class);

        self::assertCount(1, $commands);
        self::assertInstanceOf(Route::class, $command = $commands[0]);

        // Optional, single-value argument.
        $argument = $command->getArgument('optionalArgument');

        self::assertInstanceOf(ArgumentParameter::class, $argument);
        self::assertSame(ArgumentMode::OPTIONAL, $argument->getMode());
        self::assertSame(ArgumentValueMode::DEFAULT, $argument->getValueMode());
        self::assertFalse($argument->hasCast());

        // Optional, single-value, long-only option (no short names, no valid values).
        $option = $command->getOption('optionalOption');

        self::assertInstanceOf(OptionParameter::class, $option);
        self::assertSame(OptionMode::OPTIONAL, $option->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $option->getValueMode());
        self::assertSame([], $option->getShortNames());
        self::assertSame([], $option->getValidValues());
        self::assertFalse($option->hasCast());

        // Valueless (NONE) flag option.
        $flag = $command->getOption('flag');

        self::assertInstanceOf(OptionParameter::class, $flag);
        self::assertSame(OptionMode::OPTIONAL, $flag->getMode());
        self::assertSame(OptionValueMode::NONE, $flag->getValueMode());
    }

    /**
     * The attribute path converts a full matrix of argument and option modes/value-modes
     * (mirroring the Java CliRoutingCombinationsController) into data-class parameters with
     * the expected modes, value modes, short names, valid values, default, and display name.
     *
     * @throws ReflectionException
     */
    public function testGetCommandsWithParameterCombinations(): void
    {
        $collector = new AttributeRouteCollector(
            attributes: new Collector(),
            reflection: new Reflector()
        );

        $commands = $collector->getRoutes(CommandWithParameterCombinationsFixture::class);

        self::assertCount(1, $commands);
        self::assertInstanceOf(Route::class, $command = $commands[0]);

        // Required, single-value argument.
        $required = $command->getArgument('required');

        self::assertInstanceOf(ArgumentParameter::class, $required);
        self::assertSame(ArgumentMode::REQUIRED, $required->getMode());
        self::assertSame(ArgumentValueMode::DEFAULT, $required->getValueMode());

        // Optional, array argument.
        $rest = $command->getArgument('rest');

        self::assertInstanceOf(ArgumentParameter::class, $rest);
        self::assertSame(ArgumentMode::OPTIONAL, $rest->getMode());
        self::assertSame(ArgumentValueMode::ARRAY, $rest->getValueMode());

        // Required, single-value option carrying short names, valid values, default, display name.
        $format = $command->getOption('format');

        self::assertInstanceOf(OptionParameter::class, $format);
        self::assertSame(OptionMode::REQUIRED, $format->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $format->getValueMode());
        self::assertSame(['f'], $format->getShortNames());
        self::assertSame(['json', 'xml'], $format->getValidValues());
        self::assertSame('json', $format->getDefaultValue());
        self::assertSame('fmt', $format->getValueDisplayName());

        // Valueless (NONE) flag option.
        $flag = $command->getOption('flag');

        self::assertInstanceOf(OptionParameter::class, $flag);
        self::assertSame(OptionMode::OPTIONAL, $flag->getMode());
        self::assertSame(OptionValueMode::NONE, $flag->getValueMode());

        // Repeatable (ARRAY) option.
        $tags = $command->getOption('tags');

        self::assertInstanceOf(OptionParameter::class, $tags);
        self::assertSame(OptionValueMode::ARRAY, $tags->getValueMode());
    }
}
