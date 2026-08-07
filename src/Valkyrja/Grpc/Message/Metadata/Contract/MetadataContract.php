<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Metadata\Contract;

use IteratorAggregate;

/**
 * @extends IteratorAggregate<string, string[]>
 */
interface MetadataContract extends IteratorAggregate
{
    /**
     * Get the first value for a key.
     *
     * @param string $key The key (case-insensitive)
     */
    public function get(string $key): string|null;

    /**
     * Get all values for a key.
     *
     * @param string $key The key (case-insensitive)
     *
     * @return string[]
     */
    public function getAll(string $key): array;

    /**
     * Determine whether the key is present.
     *
     * @param string $key The key (case-insensitive)
     */
    public function has(string $key): bool;

    /**
     * Determine whether the key names a binary value (ends in `-bin`).
     *
     * @param string $key The key (case-insensitive)
     */
    public function isBinaryKey(string $key): bool;

    /**
     * Create a new metadata with the key set to a single value, replacing any existing values.
     *
     * @param string $key   The key (case-insensitive)
     * @param string $value The value
     */
    public function with(string $key, string $value): static;

    /**
     * Create a new metadata with the value appended to any existing values for the key.
     *
     * @param string $key   The key (case-insensitive)
     * @param string $value The value
     */
    public function withAdded(string $key, string $value): static;

    /**
     * Create a new metadata with the key removed.
     *
     * @param string $key The key (case-insensitive)
     */
    public function without(string $key): static;

    /**
     * Get an immutable snapshot as a map of lower-cased keys to value lists.
     *
     * @return array<string, string[]>
     */
    public function toArray(): array;
}
