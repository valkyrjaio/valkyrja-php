<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cli\Routing\Data;

use Valkyrja\Cli\Interaction\Option\Option;
use Valkyrja\Cli\Routing\Data\OptionParameter;
use Valkyrja\Cli\Routing\Enum\OptionMode;
use Valkyrja\Cli\Routing\Enum\OptionValueMode;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingInvalidOptionWithValueException;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingNoCastException;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingOptionValuesValidationException;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Type\Data\Cast;
use Valkyrja\Type\Enum\CastType;

/**
 * Test the OptionParameter class.
 */
final class OptionParameterTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'name';
    /** @var non-empty-string */
    protected const string DESCRIPTION = 'Test description';

    public function testDefaults(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
        );

        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getOptions());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());
    }

    public function testConstructor(): void
    {
        $name             = self::NAME;
        $description      = self::DESCRIPTION;
        $cast             = new Cast(type: CastType::bool);
        $shortName        = 's';
        $shortName2       = 'ss';
        $shortNames       = [$shortName, $shortName2];
        $mode             = OptionMode::REQUIRED;
        $valueMode        = OptionValueMode::ARRAY;
        $validValues      = ['a', 'b'];
        $option           = new Option(name: 'test', value: 'a');
        $options          = [$option];
        $defaultValue     = 'b';
        $valueDisplayName = 'test';

        $parameter = new OptionParameter(...[
            'name'             => $name,
            'description'      => $description,
            'valueDisplayName' => $valueDisplayName,
            'cast'             => $cast,
            'defaultValue'     => $defaultValue,
            'shortNames'       => $shortNames,
            'validValues'      => $validValues,
            'options'          => $options,
            'mode'             => $mode,
            'valueMode'        => $valueMode,
        ]);

        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertSame($cast, $parameter->getCast());
        self::assertSame($valueDisplayName, $parameter->getValueDisplayName());
        self::assertSame($defaultValue, $parameter->getDefaultValue());
        self::assertSame($shortNames, $parameter->getShortNames());
        self::assertSame($validValues, $parameter->getValidValues());
        self::assertSame($options, $parameter->getOptions());
        self::assertSame($mode, $parameter->getMode());
        self::assertSame($valueMode, $parameter->getValueMode());
        self::assertNotEmpty($parameter->getValues());
        self::assertSame('a', $parameter->getFirstValue());
        self::assertTrue($parameter->areValuesValid());
    }

    public function testName(): void
    {
        $name        = self::NAME;
        $name2       = 'name2';
        $description = self::DESCRIPTION;

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withName($name2);

        self::assertNotSame($parameter, $parameter2);
        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name2, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertTrue($parameter2->areValuesValid());
    }

    public function testDescription(): void
    {
        $name         = self::NAME;
        $description  = self::DESCRIPTION;
        $description2 = 'description2';

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withDescription($description2);

        self::assertNotSame($parameter, $parameter2);
        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description2, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertTrue($parameter2->areValuesValid());
    }

    public function testCast(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $cast        = new Cast(type: CastType::bool);

        $parameter  = new OptionParameter(
            name: $name,
            description: $description,
        );
        $parameter2 = $parameter->withCast($cast);

        self::assertNotSame($parameter, $parameter2);
        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertSame($cast, $parameter2->getCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertTrue($parameter2->areValuesValid());
    }

    public function testShortNames(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $shortName   = 's';
        $shortName2  = 'ss';

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withShortNames($shortName);
        $parameter3 = $parameter2->withShortNames($shortName2);
        $parameter4 = $parameter2->withAddedShortNames($shortName2);
        $parameter5 = $parameter->withAddedShortNames($shortName2);

        self::assertNotSame($parameter, $parameter2);
        self::assertNotSame($parameter2, $parameter3);
        self::assertNotSame($parameter2, $parameter4);
        self::assertNotSame($parameter, $parameter5);

        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertSame(['s'], $parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertTrue($parameter2->areValuesValid());

        self::assertSame($name, $parameter3->getName());
        self::assertSame($description, $parameter3->getDescription());
        self::assertFalse($parameter3->hasCast());
        self::assertFalse($parameter3->hasValueDisplayName());
        self::assertFalse($parameter3->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter3->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter3->getValueMode());
        self::assertEmpty($parameter3->getValidValues());
        self::assertSame(['ss'], $parameter3->getShortNames());
        self::assertEmpty($parameter3->getOptions());
        self::assertEmpty($parameter3->getValues());
        self::assertFalse($parameter3->hasFirstValue());
        self::assertTrue($parameter3->areValuesValid());

        self::assertSame($name, $parameter4->getName());
        self::assertSame($description, $parameter4->getDescription());
        self::assertFalse($parameter4->hasCast());
        self::assertFalse($parameter4->hasValueDisplayName());
        self::assertFalse($parameter4->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter4->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter4->getValueMode());
        self::assertEmpty($parameter4->getValidValues());
        self::assertSame(['s', 'ss'], $parameter4->getShortNames());
        self::assertEmpty($parameter4->getOptions());
        self::assertEmpty($parameter4->getValues());
        self::assertFalse($parameter4->hasFirstValue());
        self::assertTrue($parameter4->areValuesValid());

        self::assertSame($name, $parameter5->getName());
        self::assertSame($description, $parameter5->getDescription());
        self::assertFalse($parameter5->hasCast());
        self::assertFalse($parameter5->hasValueDisplayName());
        self::assertFalse($parameter5->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter5->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter5->getValueMode());
        self::assertEmpty($parameter5->getValidValues());
        self::assertSame(['ss'], $parameter5->getShortNames());
        self::assertEmpty($parameter5->getOptions());
        self::assertEmpty($parameter5->getValues());
        self::assertFalse($parameter5->hasFirstValue());
        self::assertTrue($parameter5->areValuesValid());
    }

    public function testMode(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $mode        = OptionMode::REQUIRED;

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withMode($mode);

        self::assertNotSame($parameter, $parameter2);
        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::REQUIRED, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertFalse($parameter2->areValuesValid());
    }

    public function testValueMode(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $mode        = OptionValueMode::ARRAY;

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withValueMode($mode);

        self::assertNotSame($parameter, $parameter2);
        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::ARRAY, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertTrue($parameter2->areValuesValid());
    }

    public function testValueDisplayName(): void
    {
        $name             = self::NAME;
        $description      = self::DESCRIPTION;
        $valueDisplayName = 'test';

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withValueDisplayName($valueDisplayName);
        $parameter3 = $parameter2->withValueDisplayName('');

        self::assertNotSame($parameter, $parameter2);
        self::assertNotSame($parameter2, $parameter3);

        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertSame($valueDisplayName, $parameter2->getValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertTrue($parameter2->areValuesValid());

        self::assertSame($name, $parameter3->getName());
        self::assertSame($description, $parameter3->getDescription());
        self::assertFalse($parameter3->hasCast());
        self::assertFalse($parameter3->hasValueDisplayName());
        self::assertFalse($parameter3->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter3->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter3->getValueMode());
        self::assertEmpty($parameter3->getValidValues());
        self::assertEmpty($parameter3->getShortNames());
        self::assertEmpty($parameter3->getOptions());
        self::assertEmpty($parameter3->getValues());
        self::assertFalse($parameter3->hasFirstValue());
        self::assertTrue($parameter3->areValuesValid());
    }

    public function testDefaultValue(): void
    {
        $name         = self::NAME;
        $description  = self::DESCRIPTION;
        $defaultValue = 'test';

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withDefaultValue($defaultValue);
        $parameter3 = $parameter2->withDefaultValue('');

        self::assertNotSame($parameter, $parameter2);
        self::assertNotSame($parameter2, $parameter3);

        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertSame($defaultValue, $parameter2->getDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertTrue($parameter2->areValuesValid());

        self::assertSame($name, $parameter3->getName());
        self::assertSame($description, $parameter3->getDescription());
        self::assertFalse($parameter3->hasCast());
        self::assertFalse($parameter3->hasValueDisplayName());
        self::assertFalse($parameter3->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter3->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter3->getValueMode());
        self::assertEmpty($parameter3->getValidValues());
        self::assertEmpty($parameter3->getShortNames());
        self::assertEmpty($parameter3->getOptions());
        self::assertEmpty($parameter3->getValues());
        self::assertFalse($parameter3->hasFirstValue());
        self::assertTrue($parameter3->areValuesValid());
    }

    public function testValidValues(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $validValue  = 'test';
        $validValue2 = 'test2';
        $validValue3 = 'test3';

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withValidValues($validValue);
        $parameter3 = $parameter2->withAddedValidValues($validValue2);
        $parameter4 = $parameter->withAddedValidValues($validValue2);
        $parameter5 = $parameter3->withValidValues($validValue3);

        self::assertNotSame($parameter, $parameter2);
        self::assertNotSame($parameter2, $parameter3);

        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertSame([$validValue], $parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertEmpty($parameter2->getOptions());
        self::assertEmpty($parameter2->getValues());
        self::assertFalse($parameter2->hasFirstValue());
        self::assertTrue($parameter2->areValuesValid());

        self::assertSame($name, $parameter3->getName());
        self::assertSame($description, $parameter3->getDescription());
        self::assertFalse($parameter3->hasCast());
        self::assertFalse($parameter3->hasValueDisplayName());
        self::assertFalse($parameter3->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter3->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter3->getValueMode());
        self::assertSame([$validValue, $validValue2], $parameter3->getValidValues());
        self::assertEmpty($parameter3->getShortNames());
        self::assertEmpty($parameter3->getOptions());
        self::assertEmpty($parameter3->getValues());
        self::assertFalse($parameter3->hasFirstValue());
        self::assertTrue($parameter3->areValuesValid());

        self::assertSame($name, $parameter4->getName());
        self::assertSame($description, $parameter4->getDescription());
        self::assertFalse($parameter4->hasCast());
        self::assertFalse($parameter4->hasValueDisplayName());
        self::assertFalse($parameter4->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter4->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter4->getValueMode());
        self::assertSame([$validValue2], $parameter4->getValidValues());
        self::assertEmpty($parameter4->getShortNames());
        self::assertEmpty($parameter4->getOptions());
        self::assertEmpty($parameter4->getValues());
        self::assertFalse($parameter4->hasFirstValue());
        self::assertTrue($parameter4->areValuesValid());

        self::assertSame($name, $parameter5->getName());
        self::assertSame($description, $parameter5->getDescription());
        self::assertFalse($parameter5->hasCast());
        self::assertFalse($parameter5->hasValueDisplayName());
        self::assertFalse($parameter5->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter5->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter5->getValueMode());
        self::assertSame([$validValue3], $parameter5->getValidValues());
        self::assertEmpty($parameter5->getShortNames());
        self::assertEmpty($parameter5->getOptions());
        self::assertEmpty($parameter5->getValues());
        self::assertFalse($parameter5->hasFirstValue());
        self::assertTrue($parameter5->areValuesValid());
    }

    public function testOptions(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $option      = new Option('name', 'value');
        $option2     = new Option('name', 'value2');
        $option3     = new Option('name', 'value3');

        $parameter  = new OptionParameter(
            name: $name,
            description: $description
        );
        $parameter2 = $parameter->withOptions($option);
        $parameter3 = $parameter2->withAddedOptions($option2);
        $parameter4 = $parameter->withAddedOptions($option2);
        $parameter5 = $parameter3->withOptions($option3);

        self::assertNotSame($parameter, $parameter2);
        self::assertNotSame($parameter2, $parameter3);

        self::assertSame($name, $parameter->getName());
        self::assertSame($description, $parameter->getDescription());
        self::assertFalse($parameter->hasCast());
        self::assertFalse($parameter->hasValueDisplayName());
        self::assertFalse($parameter->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter->getValueMode());
        self::assertEmpty($parameter->getValidValues());
        self::assertEmpty($parameter->getShortNames());
        self::assertEmpty($parameter->getOptions());
        self::assertEmpty($parameter->getValues());
        self::assertFalse($parameter->hasFirstValue());
        self::assertTrue($parameter->areValuesValid());

        self::assertSame($name, $parameter2->getName());
        self::assertSame($description, $parameter2->getDescription());
        self::assertFalse($parameter2->hasCast());
        self::assertFalse($parameter2->hasValueDisplayName());
        self::assertFalse($parameter2->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter2->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter2->getValueMode());
        self::assertEmpty($parameter2->getValidValues());
        self::assertEmpty($parameter2->getShortNames());
        self::assertSame([$option], $parameter2->getOptions());
        self::assertSame(['value'], $parameter2->getValues());
        self::assertSame('value', $parameter2->getFirstValue());
        self::assertTrue($parameter2->areValuesValid());

        self::assertSame($name, $parameter3->getName());
        self::assertSame($description, $parameter3->getDescription());
        self::assertFalse($parameter3->hasCast());
        self::assertFalse($parameter3->hasValueDisplayName());
        self::assertFalse($parameter3->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter3->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter3->getValueMode());
        self::assertEmpty($parameter3->getValidValues());
        self::assertEmpty($parameter3->getShortNames());
        self::assertSame([$option, $option2], $parameter3->getOptions());
        self::assertSame(['value', 'value2'], $parameter3->getValues());
        self::assertSame('value', $parameter3->getFirstValue());
        self::assertFalse($parameter3->areValuesValid());

        self::assertSame($name, $parameter4->getName());
        self::assertSame($description, $parameter4->getDescription());
        self::assertFalse($parameter4->hasCast());
        self::assertFalse($parameter4->hasValueDisplayName());
        self::assertFalse($parameter4->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter4->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter4->getValueMode());
        self::assertEmpty($parameter4->getValidValues());
        self::assertEmpty($parameter4->getShortNames());
        self::assertSame([$option2], $parameter4->getOptions());
        self::assertSame(['value2'], $parameter4->getValues());
        self::assertSame('value2', $parameter4->getFirstValue());
        self::assertTrue($parameter4->areValuesValid());

        self::assertSame($name, $parameter5->getName());
        self::assertSame($description, $parameter5->getDescription());
        self::assertFalse($parameter5->hasCast());
        self::assertFalse($parameter5->hasValueDisplayName());
        self::assertFalse($parameter5->hasDefaultValue());
        self::assertSame(OptionMode::OPTIONAL, $parameter5->getMode());
        self::assertSame(OptionValueMode::DEFAULT, $parameter5->getValueMode());
        self::assertEmpty($parameter5->getValidValues());
        self::assertEmpty($parameter5->getShortNames());
        self::assertSame([$option3], $parameter5->getOptions());
        self::assertSame(['value3'], $parameter5->getValues());
        self::assertSame('value3', $parameter5->getFirstValue());
        self::assertTrue($parameter5->areValuesValid());
    }

    public function testGetFirstValueIsEmptyStringWhenNoArguments(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;

        $parameter  = new OptionParameter(
            name: $name,
            description: $description,
        );

        self::assertSame('', $parameter->getFirstValue());
    }

    public function testInvalidOptionsWithValues(): void
    {
        $this->expectException(CliRoutingInvalidOptionWithValueException::class);

        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $option      = new Option('name', 'value');

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
            valueMode: OptionValueMode::NONE,
        );

        $parameter->withOptions($option);
    }

    public function testValidOptionsWithoutValues(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $option      = new Option('name');

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
            valueMode: OptionValueMode::NONE,
        );

        $parameter2 = $parameter->withOptions($option);

        self::assertNotSame($parameter, $parameter2);

        self::assertSame([$option], $parameter2->getOptions());
    }

    public function testInvalidWithAddedOptionsWithValues(): void
    {
        $this->expectException(CliRoutingInvalidOptionWithValueException::class);

        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $option      = new Option('name', 'value');

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
            valueMode: OptionValueMode::NONE,
        );

        $parameter->withAddedOptions($option);
    }

    public function testWithAddedOptionsWithoutValues(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $option      = new Option('name');

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
            valueMode: OptionValueMode::NONE,
        );

        $parameter2 = $parameter->withAddedOptions($option);

        self::assertNotSame($parameter, $parameter2);

        self::assertSame([$option], $parameter2->getOptions());
    }

    public function testGetValuesReturnsEachRawValue(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $cast        = new Cast(type: CastType::int);
        $option      = new Option('option', '1');
        $option2     = new Option('option2', '2');
        $option3     = new Option('option3');

        $parameter  = new OptionParameter(
            name: $name,
            description: $description,
            cast: $cast,
        );
        $parameter2 = $parameter->withOptions($option, $option2);
        $parameter4 = $parameter2
            ->withoutCast()
            ->withOptions($option3);

        self::assertEmpty($parameter->getValues());

        self::assertNotEmpty($parameter2->getValues());
        self::assertSame(['1', '2'], $parameter2->getValues());
        self::assertSame([''], $parameter4->getValues());
    }

    public function testGetCastThrowsWhenNoCastSet(): void
    {
        $this->expectException(CliRoutingNoCastException::class);
        $this->expectExceptionMessage('No cast exists');

        $name        = self::NAME;
        $description = self::DESCRIPTION;

        $parameter  = new OptionParameter(
            name: $name,
            description: $description,
        );

        $parameter->getCast();
    }

    public function testAreValuesValid(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $option      = new Option('option', '1');
        $option2     = new Option('option2', '2');

        $parameter  = new OptionParameter(
            name: $name,
            description: $description,
        );
        $parameter2 = $parameter->withOptions($option);
        $parameter3 = $parameter->withOptions($option, $option2);
        $parameter4 = $parameter3->withValueMode(OptionValueMode::ARRAY);
        $parameter5 = $parameter->withMode(OptionMode::REQUIRED);
        $parameter6 = $parameter3->withMode(OptionMode::REQUIRED);

        self::assertTrue($parameter->areValuesValid());
        self::assertTrue($parameter2->areValuesValid());
        self::assertFalse($parameter3->areValuesValid());
        self::assertTrue($parameter4->areValuesValid());
        self::assertFalse($parameter5->areValuesValid());
        self::assertFalse($parameter6->areValuesValid());
    }

    public function testValidateValuesException(): void
    {
        $this->expectException(CliRoutingOptionValuesValidationException::class);

        $name        = self::NAME;
        $description = self::DESCRIPTION;

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
            mode: OptionMode::REQUIRED,
        );

        $parameter->validateValues();
    }

    public function testValidateValues(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
        );

        self::assertSame($parameter, $parameter->validateValues());
    }

    public function testAreValuesValidWithValidValues(): void
    {
        $name         = self::NAME;
        $description  = self::DESCRIPTION;
        $validValue   = new Option('option', 'a');
        $validValue2  = new Option('option2', 'b');
        $invalidValue = new Option('option3', 'c');

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
            validValues: ['a', 'b'],
        );

        // Empty valid values impose no constraint on the bound value
        $unconstrained = new OptionParameter(name: $name, description: $description)
            ->withOptions($invalidValue);
        // A provided value that is a member of the valid values passes
        $valid = $parameter->withOptions($validValue);
        // A provided value that is not a member of the valid values fails
        $invalid = $parameter->withOptions($invalidValue);
        // ARRAY: every provided value must be a member of the valid values
        $arrayAllValid = $parameter
            ->withValueMode(OptionValueMode::ARRAY)
            ->withOptions($validValue, $validValue2);
        // ARRAY: a single invalid value among several fails
        $arrayOneInvalid = $parameter
            ->withValueMode(OptionValueMode::ARRAY)
            ->withOptions($validValue, $validValue2, $invalidValue);
        // Non-empty valid values with no bound options impose no failure
        $noOptions = $parameter;
        // Interaction with REQUIRED: a required, member value passes
        $requiredValid = $parameter
            ->withMode(OptionMode::REQUIRED)
            ->withOptions($validValue);
        // Interaction with REQUIRED: a required, non-member value fails
        $requiredInvalid = $parameter
            ->withMode(OptionMode::REQUIRED)
            ->withOptions($invalidValue);

        self::assertTrue($unconstrained->areValuesValid());
        self::assertTrue($valid->areValuesValid());
        self::assertFalse($invalid->areValuesValid());
        self::assertTrue($arrayAllValid->areValuesValid());
        self::assertFalse($arrayOneInvalid->areValuesValid());
        self::assertTrue($noOptions->areValuesValid());
        self::assertTrue($requiredValid->areValuesValid());
        self::assertFalse($requiredInvalid->areValuesValid());
    }

    public function testValidateValuesThrowsOnInvalidValue(): void
    {
        $this->expectException(CliRoutingOptionValuesValidationException::class);

        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $option      = new Option('option', 'c');

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
            validValues: ['a', 'b'],
        );

        $parameter->withOptions($option)->validateValues();
    }

    public function testValidateValuesPassesWithValidValue(): void
    {
        $name        = self::NAME;
        $description = self::DESCRIPTION;
        $option      = new Option('option', 'a');

        $parameter = new OptionParameter(
            name: $name,
            description: $description,
            validValues: ['a', 'b'],
        );

        $valid = $parameter->withOptions($option);

        self::assertSame($valid, $valid->validateValues());
    }

    public function testIsProvidedAndHasFirstValue(): void
    {
        $parameter = new OptionParameter(name: self::NAME, description: self::DESCRIPTION);

        self::assertFalse($parameter->isProvided());
        self::assertFalse($parameter->hasFirstValue());

        $flag = $parameter->withOptions(new Option(self::NAME));

        self::assertTrue($flag->isProvided());
        self::assertFalse($flag->hasFirstValue());

        $withValue = $parameter->withOptions(new Option(self::NAME, 'value'));

        self::assertTrue($withValue->isProvided());
        self::assertTrue($withValue->hasFirstValue());
    }
}
