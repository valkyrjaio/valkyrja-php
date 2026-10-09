<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Cli\Routing\Caster;

use Valkyrja\Cli\Interaction\Argument\Argument;
use Valkyrja\Cli\Interaction\Option\Option;
use Valkyrja\Cli\Routing\Caster\Caster;
use Valkyrja\Cli\Routing\Data\ArgumentParameter;
use Valkyrja\Cli\Routing\Data\OptionParameter;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Throwable\Exception\ContainerInvalidReferenceException;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Type\Constant\CastArgument;
use Valkyrja\Type\Data\Cast;
use Valkyrja\Type\Enum\CastType;
use Valkyrja\Type\Int\IntT;
use Valkyrja\Type\Provider\TypeServiceProvider;

final class CasterTest extends TestCase
{
    public function testParameterWithNoCastGivesEachRawValue(): void
    {
        $parameter = new ArgumentParameter(name: 'target', description: 'The target')
            ->withArguments(new Argument('a'), new Argument('b'));

        self::assertSame(['a', 'b'], new Caster(container: $this->containerWithTypes())->getCastValues($parameter));
    }

    public function testCastThatConvertsGivesThePlainValue(): void
    {
        $parameter = new ArgumentParameter(
            name: 'target',
            description: 'The target',
            cast: new Cast(type: CastType::int),
        )->withArguments(new Argument('1'), new Argument('2'));

        self::assertSame([1, 2], new Caster(container: $this->containerWithTypes())->getCastValues($parameter));
    }

    public function testCastThatDoesNotConvertGivesTheType(): void
    {
        $parameter = new ArgumentParameter(
            name: 'target',
            description: 'The target',
            cast: new Cast(type: CastType::int, convert: false),
        )->withArguments(new Argument('1'));

        $values = new Caster(container: $this->containerWithTypes())->getCastValues($parameter);

        self::assertInstanceOf(IntT::class, $values[0]);
        self::assertSame(1, $values[0]->asValue());
    }

    public function testOptionParameterTakesTheSameCast(): void
    {
        $parameter = new OptionParameter(
            name: 'target',
            description: 'The target',
            cast: new Cast(type: CastType::int),
        )->withOptions(new Option('target', '3'));

        self::assertSame([3], new Caster(container: $this->containerWithTypes())->getCastValues($parameter));
    }

    public function testSingletonBindingBuildsOneTypeForEachValue(): void
    {
        $container = new Container();
        $container->bindSingleton(
            IntT::class,
            static fn (ContainerContract $container, array $arguments): IntT => IntT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );

        $parameter = new ArgumentParameter(
            name: 'target',
            description: 'The target',
            cast: new Cast(type: CastType::int),
        )->withArguments(new Argument('1'), new Argument('2'));

        self::assertSame([1, 2], new Caster(container: $container)->getCastValues($parameter));
    }

    public function testCastTypeThatNoServiceHoldsRaises(): void
    {
        $parameter = new ArgumentParameter(
            name: 'target',
            description: 'The target',
            cast: new Cast(type: CastType::int),
        )->withArguments(new Argument('1'));

        $this->expectException(ContainerInvalidReferenceException::class);

        new Caster(container: new Container())->getCastValues($parameter);
    }

    /**
     * Get a container that holds every type that a cast type names.
     */
    protected function containerWithTypes(): ContainerContract
    {
        $container = new Container();

        foreach (new TypeServiceProvider()->publishers() as $publisher) {
            $publisher($container);
        }

        return $container;
    }
}
