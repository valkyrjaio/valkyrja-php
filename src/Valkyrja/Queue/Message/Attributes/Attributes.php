<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Attributes;

use Override;
use Valkyrja\Queue\Message\Attributes\Contract\AttributesContract;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidAttributeNameException;

use function array_filter;
use function array_values;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function strtolower;

use const ARRAY_FILTER_USE_KEY;

class Attributes implements AttributesContract
{
    /** @var array<non-empty-lowercase-string, string[]> */
    protected array $attributes = [];

    /**
     * @param array<non-empty-string, string[]> $attributes The attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes($attributes);
    }

    /**
     * Create a new instance from a decoded envelope's attributes object.
     *
     * Values arrive from the wire as a list of strings; a bare scalar is
     * accepted and promoted to a single-element list so a producer in a laxer
     * language cannot break the consumer.
     *
     * @param array<array-key, mixed> $data The data to create from
     */
    public static function fromArray(array $data): static
    {
        $attributes = [];

        /**
         * @var array-key $name
         * @var mixed     $values
         */
        foreach ($data as $name => $values) {
            $attributes[(string) $name] = static::normalizeValues($values);
        }

        /** @var array<non-empty-string, string[]> $attributes */
        return new static($attributes);
    }

    /**
     * Normalize a wire value into a list of strings.
     *
     * @return string[]
     */
    protected static function normalizeValues(mixed $values): array
    {
        if (is_array($values)) {
            $normalized = [];

            /** @var mixed $value */
            foreach ($values as $value) {
                $normalized[] = static::normalizeValue($value);
            }

            return $normalized;
        }

        return [static::normalizeValue($values)];
    }

    /**
     * Normalize a single wire value into a string.
     */
    protected static function normalizeValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            throw new QueueMessageInvalidAttributeNameException('Attribute values must be scalar');
        }

        return (string) $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $name): bool
    {
        return isset($this->attributes[strtolower($name)]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(string $name): array
    {
        return $this->attributes[strtolower($name)]
            ?? [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getFirst(string $name): string|null
    {
        return $this->get($name)[0]
            ?? null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getLine(string $name): string
    {
        return implode(',', $this->get($name));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAll(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getOnly(string ...$names): array
    {
        $normalized = $this->normalizeNames(...$names);

        return array_filter(
            $this->attributes,
            static fn (string $name): bool => in_array($name, $normalized, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAllExcept(string ...$names): array
    {
        $normalized = $this->normalizeNames(...$names);

        return array_filter(
            $this->attributes,
            static fn (string $name): bool => ! in_array($name, $normalized, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAttribute(string $name, string ...$values): static
    {
        $new = clone $this;

        $new->attributes[$new->normalizeName($name)] = array_values($values);

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedAttribute(string $name, string ...$values): static
    {
        $new = clone $this;

        $normalizedName = $new->normalizeName($name);

        $new->attributes[$normalizedName] = [
            ...$new->get($normalizedName),
            ...array_values($values),
        ];

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutAttributes(string ...$names): static
    {
        $new = clone $this;

        foreach ($names as $name) {
            unset($new->attributes[strtolower($name)]);
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asArray(): array
    {
        return $this->attributes;
    }

    /**
     * Normalize a set of attribute names.
     *
     * @param string ...$names The attribute names
     *
     * @return non-empty-lowercase-string[]
     */
    protected function normalizeNames(string ...$names): array
    {
        $normalized = [];

        foreach ($names as $name) {
            $normalized[] = $this->normalizeName($name);
        }

        return $normalized;
    }

    /**
     * Normalize an attribute name, rejecting an empty one.
     *
     * @param string $name The attribute name
     *
     * @psalm-assert non-empty-string $name
     *
     * @phpstan-assert non-empty-string $name
     *
     * @return non-empty-lowercase-string
     */
    protected function normalizeName(string $name): string
    {
        if ($name === '') {
            throw new QueueMessageInvalidAttributeNameException('Attribute name must not be empty');
        }

        return strtolower($name);
    }

    /**
     * Set the attributes, normalizing every name.
     *
     * An all-digit name is legal on the wire, and PHP hands such an array key
     * back as an int, so the key is cast rather than assumed to be a string.
     *
     * @param array<array-key, string[]> $attributes The attributes
     */
    protected function setAttributes(array $attributes): void
    {
        $normalized = [];

        foreach ($attributes as $name => $values) {
            $normalized[$this->normalizeName((string) $name)] = array_values($values);
        }

        $this->attributes = $normalized;
    }
}
