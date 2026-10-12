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

use __PHP_Incomplete_Class;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Valkyrja\Container\Manager\Container;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
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
use Valkyrja\Type\Throwable\Exception\TypeMissingCastArgumentException;

use function array_keys;
use function count;
use function serialize;

final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = TypeServiceProvider::class;

    public function testPublishersHoldEveryTypeThatACastTypeNames(): void
    {
        $publishers = new TypeServiceProvider()->publishers();

        foreach (CastType::cases() as $case) {
            self::assertArrayHasKey($case->value, $publishers);
            self::assertIsCallable($publishers[$case->value]);
        }

        self::assertCount(count(CastType::cases()), $publishers);
    }

    public function testEachPublisherBindsOnlyItsOwnType(): void
    {
        foreach (new TypeServiceProvider()->publishers() as $id => $publisher) {
            $container = new Container();

            $publisher($container);

            self::assertTrue($container->isService($id));

            foreach (array_keys(new TypeServiceProvider()->publishers()) as $other) {
                if ($other !== $id) {
                    self::assertFalse($container->isService($other));
                }
            }
        }
    }

    public function testTheArrayTFactoryReadsTheCastArgument(): void
    {
        self::assertSame(['a'], $this->build(ArrayT::class, ['a'])->asValue());
    }

    public function testTheBoolTFactoryReadsTheCastArgument(): void
    {
        self::assertTrue($this->build(BoolT::class, '1')->asValue());
    }

    public function testTheFalseTFactoryReadsTheCastArgument(): void
    {
        self::assertFalse($this->build(FalseT::class, false)->asValue());
    }

    public function testTheFloatTFactoryReadsTheCastArgument(): void
    {
        self::assertSame(1.5, $this->build(FloatT::class, '1.5')->asValue());
    }

    public function testTheIntTFactoryReadsTheCastArgument(): void
    {
        self::assertSame(1, $this->build(IntT::class, '1')->asValue());
    }

    public function testTheJsonFactoryReadsTheCastArgument(): void
    {
        self::assertSame(['a' => 1], $this->build(Json::class, '{"a":1}')->asValue());
    }

    public function testTheJsonObjectFactoryReadsTheCastArgument(): void
    {
        self::assertSame(1, $this->build(JsonObject::class, '{"a":1}')->asValue()->a);
    }

    public function testTheNullTFactoryReadsTheCastArgument(): void
    {
        self::assertNull($this->build(NullT::class, null)->asValue());
    }

    public function testTheObjectTFactoryReadsTheCastArgument(): void
    {
        self::assertSame(1, $this->build(ObjectT::class, '{"a":1}')->asValue()->a);
    }

    public function testTheSerializedObjectFactoryReadsTheCastArgument(): void
    {
        self::assertInstanceOf(
            __PHP_Incomplete_Class::class,
            $this->build(SerializedObject::class, serialize(new stdClass()))->asValue()
        );
    }

    public function testTheStringTFactoryReadsTheCastArgument(): void
    {
        self::assertSame('a', $this->build(StringT::class, 'a')->asValue());
    }

    public function testTheTrueTFactoryReadsTheCastArgument(): void
    {
        self::assertTrue($this->build(TrueT::class, true)->asValue());
    }

    #[DataProvider('providesDataProvider')]
    public function testEveryFactoryRaisesWithNoCastArgument(string $id): void
    {
        $container = new Container();

        foreach (new TypeServiceProvider()->publishers() as $publisherId => $publisher) {
            if ($publisherId === $id) {
                $publisher($container);
            }
        }

        $this->expectException(TypeMissingCastArgumentException::class);
        $this->expectExceptionMessage('A cast type takes the raw value under the `value` argument');

        $container->getService($id);
    }

    public function testTheContainerBuildsOneTypeForEachCall(): void
    {
        $container = new Container();

        TypeServiceProvider::publishIntT($container);

        $first  = $container->getService(IntT::class, [CastArgument::VALUE => '1']);
        $second = $container->getService(IntT::class, [CastArgument::VALUE => '2']);

        self::assertNotSame($first, $second);
        self::assertSame(1, $first->asValue());
        self::assertSame(2, $second->asValue());
    }

    /**
     * Build a type through the container, the way a caster does.
     *
     * @template T of object
     *
     * @param class-string<T> $id    The type id
     * @param mixed           $value The raw value
     *
     * @return T
     */
    protected function build(string $id, mixed $value): object
    {
        $container = new Container();

        foreach (new TypeServiceProvider()->publishers() as $publisherId => $publisher) {
            if ($publisherId === $id) {
                $publisher($container);
            }
        }

        return $container->getService($id, [CastArgument::VALUE => $value]);
    }
}
