<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Payload\Contract;

interface PayloadContract
{
    /**
     * Determine if a param exists.
     *
     * @param array-key $key The param name
     */
    public function has(string|int $key): bool;

    /**
     * Get a param.
     *
     * @param array-key $key The param name
     */
    public function get(string|int $key): self|float|bool|int|string|null;

    /**
     * Get all the params.
     *
     * @return array<array-key, scalar|self|null>
     */
    public function getAll(): array;

    /**
     * Get only the specified params.
     *
     * @param array-key ...$keys The param keys
     *
     * @return array<array-key, scalar|self|null>
     */
    public function getOnly(string|int ...$keys): array;

    /**
     * Get all the params except the specified ones.
     *
     * @param array-key ...$keys The param names
     *
     * @return array<array-key, scalar|self|null>
     */
    public function getAllExcept(string|int ...$keys): array;

    /**
     * Get a new instance with the specified params.
     *
     * The new params decide whether the node is a list. Replacing them with an
     * empty array keeps the shape the node already had, because emptying a node
     * is not reshaping it.
     *
     * @param array<array-key, scalar|self|null> $params The params
     */
    public function with(array $params): static;

    /**
     * Get a new instance with the added params.
     *
     * The merged params decide whether the node is a list, so adding a string
     * key to a list makes it a map. Adding nothing to an empty node keeps the
     * shape it already had.
     *
     * @param array<array-key, scalar|self|null> $params The params
     */
    public function withAdded(array $params): static;

    /**
     * Get the wire representation: a plain, recursively flattened JSON object.
     *
     * @return array<array-key, scalar|array<array-key, mixed>|null>
     */
    public function asArray(): array;

    /**
     * Whether this node encodes as a JSON array rather than a JSON object.
     *
     * `json_decode` with associative arrays turns both into a PHP array, so
     * nothing can tell an empty map from an empty list once that has happened.
     * The encoder asks this instead of guessing from the keys. A node read from
     * the wire answers with the shape it arrived as, and a mutator recomputes
     * the answer from the params it is given.
     */
    public function isList(): bool;
}
