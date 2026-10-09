<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Type\Provider;

use Valkyrja\Container\Manager\Container;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Type\Array\ArrayT;
use Valkyrja\Type\Bool\BoolT;
use Valkyrja\Type\Bool\FalseT;
use Valkyrja\Type\Bool\TrueT;
use Valkyrja\Type\Constant\CastArgument;
use Valkyrja\Type\Enum\CastType;
use Valkyrja\Type\Float\FloatT;
use Valkyrja\Type\Int\IntT;
use Valkyrja\Type\Json\Json;
use Valkyrja\Type\Json\JsonObject;
use Valkyrja\Type\Null\NullT;
use Valkyrja\Type\Object\ObjectT;
use Valkyrja\Type\Object\SerializedObject;
use Valkyrja\Type\Provider\TypeServiceProvider;
use Valkyrja\Type\String\StringT;

/**
 * Test the type service provider.
 */
final class TypeServiceProviderTest extends TestCase
{
    public function testPublishersHoldEveryTypeThatACastTypeNames(): void
    {
        $publishers = new TypeServiceProvider()->publishers();

        foreach (CastType::cases() as $case) {
            self::assertArrayHasKey($case->value, $publishers);
        }
    }

    public function testPublishTypesBindsEveryType(): void
    {
        $container = new Container();

        TypeServiceProvider::publishTypes($container);

        foreach (
            [
                ArrayT::class,
                BoolT::class,
                FalseT::class,
                FloatT::class,
                IntT::class,
                Json::class,
                JsonObject::class,
                NullT::class,
                ObjectT::class,
                SerializedObject::class,
                StringT::class,
                TrueT::class,
            ] as $type
        ) {
            self::assertTrue($container->isService($type));
        }
    }

    public function testEachBoundTypeReadsTheCastArgument(): void
    {
        $container = new Container();

        TypeServiceProvider::publishTypes($container);

        self::assertSame(1, $container->getService(IntT::class, [CastArgument::VALUE => '1'])->asValue());
        self::assertSame('a', $container->getService(StringT::class, [CastArgument::VALUE => 'a'])->asValue());
        self::assertSame(1.5, $container->getService(FloatT::class, [CastArgument::VALUE => '1.5'])->asValue());
        self::assertTrue($container->getService(BoolT::class, [CastArgument::VALUE => '1'])->asValue());
        self::assertSame(['a'], $container->getService(ArrayT::class, [CastArgument::VALUE => ['a']])->asValue());
        self::assertTrue($container->getService(TrueT::class, [CastArgument::VALUE => true])->asValue());
        self::assertFalse($container->getService(FalseT::class, [CastArgument::VALUE => false])->asValue());
        self::assertNull($container->getService(NullT::class, [CastArgument::VALUE => null])->asValue());
    }

    public function testTheContainerBuildsOneTypeForEachCall(): void
    {
        $container = new Container();

        TypeServiceProvider::publishTypes($container);

        $first  = $container->getService(IntT::class, [CastArgument::VALUE => '1']);
        $second = $container->getService(IntT::class, [CastArgument::VALUE => '2']);

        self::assertNotSame($first, $second);
        self::assertSame(1, $first->asValue());
        self::assertSame(2, $second->asValue());
    }
}
