<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Data\Abstract;

use Override;
use Valkyrja\Cli\Interaction\Argument\Contract\ArgumentContract;
use Valkyrja\Cli\Interaction\Option\Contract\OptionContract;
use Valkyrja\Cli\Routing\Data\Contract\ParameterContract;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingNoCastException;
use Valkyrja\Type\Data\Cast;

abstract class Parameter implements ParameterContract
{
    /**
     * @param non-empty-string $name        The name
     * @param non-empty-string $description The description
     */
    public function __construct(
        protected string $name,
        protected string $description,
        protected Cast|null $cast = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withName(string $name): static
    {
        $new = clone $this;

        $new->name = $name;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasCast(): bool
    {
        return $this->cast !== null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCast(): Cast
    {
        return $this->cast
            ?? throw new CliRoutingNoCastException('No cast exists');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withCast(Cast $cast): static
    {
        $new = clone $this;

        $new->cast = $cast;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutCast(): static
    {
        $new = clone $this;

        $new->cast = null;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withDescription(string $description): static
    {
        $new = clone $this;

        $new->description = $description;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasFirstValue(): bool
    {
        return $this->getFirstValue() !== '';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    abstract public function getValues(): array;

    /**
     * Get the raw value of each parameter.
     *
     * @param array<ArgumentContract|OptionContract> $parameters The parameters
     *
     * @return array<array-key, string>
     */
    protected function getValuesOfParameters(array $parameters): array
    {
        $values = [];

        foreach ($parameters as $parameter) {
            $values[] = $parameter->getValue();
        }

        return $values;
    }
}
