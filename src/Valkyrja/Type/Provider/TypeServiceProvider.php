<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Type\Provider;

use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Type\Array\ArrayT;
use Valkyrja\Type\Bool\BoolT;
use Valkyrja\Type\Bool\FalseT;
use Valkyrja\Type\Bool\TrueT;
use Valkyrja\Type\Constant\CastArgument;
use Valkyrja\Type\Float\FloatT;
use Valkyrja\Type\Int\IntT;
use Valkyrja\Type\Json\Json;
use Valkyrja\Type\Json\JsonObject;
use Valkyrja\Type\Null\NullT;
use Valkyrja\Type\Object\ObjectT;
use Valkyrja\Type\Object\SerializedObject;
use Valkyrja\Type\String\StringT;
use Valkyrja\Type\Throwable\Exception\TypeMissingCastArgumentException;

use function array_key_exists;

class TypeServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the ArrayT type.
     */
    public static function publishArrayT(ContainerContract $container): void
    {
        $container->bind(
            ArrayT::class,
            static fn (ContainerContract $container, array $arguments): ArrayT => ArrayT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the BoolT type.
     */
    public static function publishBoolT(ContainerContract $container): void
    {
        $container->bind(
            BoolT::class,
            static fn (ContainerContract $container, array $arguments): BoolT => BoolT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the FalseT type.
     */
    public static function publishFalseT(ContainerContract $container): void
    {
        $container->bind(
            FalseT::class,
            static fn (ContainerContract $container, array $arguments): FalseT => FalseT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the FloatT type.
     */
    public static function publishFloatT(ContainerContract $container): void
    {
        $container->bind(
            FloatT::class,
            static fn (ContainerContract $container, array $arguments): FloatT => FloatT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the IntT type.
     */
    public static function publishIntT(ContainerContract $container): void
    {
        $container->bind(
            IntT::class,
            static fn (ContainerContract $container, array $arguments): IntT => IntT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the Json type.
     */
    public static function publishJson(ContainerContract $container): void
    {
        $container->bind(
            Json::class,
            static fn (ContainerContract $container, array $arguments): Json => Json::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the JsonObject type.
     */
    public static function publishJsonObject(ContainerContract $container): void
    {
        $container->bind(
            JsonObject::class,
            static fn (ContainerContract $container, array $arguments): JsonObject => JsonObject::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the NullT type.
     */
    public static function publishNullT(ContainerContract $container): void
    {
        $container->bind(
            NullT::class,
            static fn (ContainerContract $container, array $arguments): NullT => NullT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the ObjectT type.
     */
    public static function publishObjectT(ContainerContract $container): void
    {
        $container->bind(
            ObjectT::class,
            static fn (ContainerContract $container, array $arguments): ObjectT => ObjectT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the SerializedObject type.
     */
    public static function publishSerializedObject(ContainerContract $container): void
    {
        $container->bind(
            SerializedObject::class,
            static fn (ContainerContract $container, array $arguments): SerializedObject => SerializedObject::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the StringT type.
     */
    public static function publishStringT(ContainerContract $container): void
    {
        $container->bind(
            StringT::class,
            static fn (ContainerContract $container, array $arguments): StringT => StringT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Publish the TrueT type.
     */
    public static function publishTrueT(ContainerContract $container): void
    {
        $container->bind(
            TrueT::class,
            static fn (ContainerContract $container, array $arguments): TrueT => TrueT::fromValue(
                self::getCastArgument($arguments)
            )
        );
    }

    /**
     * Get the raw value that a cast passes to the container.
     *
     * @param array<array-key, mixed> $arguments The arguments
     *
     * @throws TypeMissingCastArgumentException
     */
    protected static function getCastArgument(array $arguments): mixed
    {
        if (!array_key_exists(CastArgument::VALUE, $arguments)) {
            throw new TypeMissingCastArgumentException(
                'A cast type takes the raw value under the `' . CastArgument::VALUE . '` argument'
            );
        }

        return $arguments[CastArgument::VALUE];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            ArrayT::class           => [self::class, 'publishArrayT'],
            BoolT::class            => [self::class, 'publishBoolT'],
            FalseT::class           => [self::class, 'publishFalseT'],
            FloatT::class           => [self::class, 'publishFloatT'],
            IntT::class             => [self::class, 'publishIntT'],
            Json::class             => [self::class, 'publishJson'],
            JsonObject::class       => [self::class, 'publishJsonObject'],
            NullT::class            => [self::class, 'publishNullT'],
            ObjectT::class          => [self::class, 'publishObjectT'],
            SerializedObject::class => [self::class, 'publishSerializedObject'],
            StringT::class          => [self::class, 'publishStringT'],
            TrueT::class            => [self::class, 'publishTrueT'],
        ];
    }
}
