<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Attributes\Contract;

interface AttributesContract
{
    /**
     * Determine if an attribute exists.
     *
     * @param non-empty-string $name The attribute name
     */
    public function has(string $name): bool;

    /**
     * Get an attribute's values, or an empty list when it does not exist.
     *
     * @param non-empty-string $name The attribute name
     *
     * @return string[]
     */
    public function get(string $name): array;

    /**
     * Get an attribute's first value, or null when it does not exist.
     *
     * @param non-empty-string $name The attribute name
     */
    public function getFirst(string $name): string|null;

    /**
     * Get an attribute's values joined as a single comma-separated line.
     *
     * @param non-empty-string $name The attribute name
     */
    public function getLine(string $name): string;

    /**
     * Get all the attributes, keyed by normalized name.
     *
     * @return array<non-empty-lowercase-string, string[]>
     */
    public function getAll(): array;

    /**
     * Get only the specified attributes.
     *
     * @param non-empty-string ...$names The attribute names
     *
     * @return array<non-empty-lowercase-string, string[]>
     */
    public function getOnly(string ...$names): array;

    /**
     * Get all the attributes except the specified ones.
     *
     * @param non-empty-string ...$names The attribute names
     *
     * @return array<non-empty-lowercase-string, string[]>
     */
    public function getAllExcept(string ...$names): array;

    /**
     * Get a new instance with the specified attribute, replacing any existing one of the same name.
     *
     * @param non-empty-string $name      The attribute name
     * @param string           ...$values The attribute values
     */
    public function withAttribute(string $name, string ...$values): static;

    /**
     * Get a new instance with the specified values appended to an attribute.
     *
     * @param non-empty-string $name      The attribute name
     * @param string           ...$values The attribute values
     */
    public function withAddedAttribute(string $name, string ...$values): static;

    /**
     * Get a new instance without the specified attributes.
     *
     * @param non-empty-string ...$names The attribute names
     */
    public function withoutAttributes(string ...$names): static;

    /**
     * Get the wire representation: a plain object of name to value list.
     *
     * @return array<non-empty-lowercase-string, string[]>
     */
    public function asArray(): array;
}
