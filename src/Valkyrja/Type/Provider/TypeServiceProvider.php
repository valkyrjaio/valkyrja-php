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

class TypeServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the type that each cast type names.
     */
    public static function publishTypes(ContainerContract $container): void
    {
        $container->bind(
            ArrayT::class,
            static fn (ContainerContract $container, array $arguments): ArrayT => ArrayT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            BoolT::class,
            static fn (ContainerContract $container, array $arguments): BoolT => BoolT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            FalseT::class,
            static fn (ContainerContract $container, array $arguments): FalseT => FalseT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            FloatT::class,
            static fn (ContainerContract $container, array $arguments): FloatT => FloatT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            IntT::class,
            static fn (ContainerContract $container, array $arguments): IntT => IntT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            Json::class,
            static fn (ContainerContract $container, array $arguments): Json => Json::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            JsonObject::class,
            static fn (ContainerContract $container, array $arguments): JsonObject => JsonObject::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            NullT::class,
            static fn (ContainerContract $container, array $arguments): NullT => NullT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            ObjectT::class,
            static fn (ContainerContract $container, array $arguments): ObjectT => ObjectT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            SerializedObject::class,
            static fn (ContainerContract $container, array $arguments): SerializedObject => SerializedObject::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            StringT::class,
            static fn (ContainerContract $container, array $arguments): StringT => StringT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
        $container->bind(
            TrueT::class,
            static fn (ContainerContract $container, array $arguments): TrueT => TrueT::fromValue(
                $arguments[CastArgument::VALUE] ?? null
            )
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        $publishers = [];

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
            $publishers[$type] = [self::class, 'publishTypes'];
        }

        return $publishers;
    }
}
