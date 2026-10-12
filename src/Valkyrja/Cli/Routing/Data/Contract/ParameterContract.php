<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Data\Contract;

use Valkyrja\Type\Data\Cast;

interface ParameterContract
{
    /**
     * Get the name.
     *
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Create a new Parameter with the specified name.
     *
     * @param non-empty-string $name The name
     */
    public function withName(string $name): static;

    /**
     * Determine if a cast exists.
     */
    public function hasCast(): bool;

    /**
     * Get the cast.
     */
    public function getCast(): Cast;

    /**
     * Create a new Parameter with the specified cast.
     *
     * @param Cast $cast The cast
     */
    public function withCast(Cast $cast): static;

    /**
     * Create a new Parameter without a cast.
     */
    public function withoutCast(): static;

    /**
     * Get the description.
     *
     * @return non-empty-string
     */
    public function getDescription(): string;

    /**
     * Create a new Parameter with the specified description.
     *
     * @param non-empty-string $description The description
     */
    public function withDescription(string $description): static;

    /**
     * Get each raw value that the invocation gave the parameter.
     *
     * @return array<array-key, string>
     */
    public function getValues(): array;

    /**
     * Determine if the input provided the parameter, with or without a value.
     */
    public function isProvided(): bool;

    /**
     * Determine if the input provided the parameter with a non-empty first value.
     */
    public function hasFirstValue(): bool;

    /**
     * Get the first item's value.
     */
    public function getFirstValue(): string;

    /**
     * Determine if the values are valid.
     */
    public function areValuesValid(): bool;

    /**
     * Validate the values.
     */
    public function validateValues(): static;
}
