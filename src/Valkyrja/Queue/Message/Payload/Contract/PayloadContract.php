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
     * @param non-empty-string|int $key The param name
     */
    public function has(string|int $key): bool;

    /**
     * Get a param.
     *
     * @param non-empty-string|int $key The param name
     */
    public function get(string|int $key): self|float|bool|int|string|null;

    /**
     * Get all the params.
     *
     * @return array<non-empty-string|int, scalar|self|null>
     */
    public function getAll(): array;

    /**
     * Get only the specified params.
     *
     * @param non-empty-string|int ...$keys The param keys
     *
     * @return array<non-empty-string|int, scalar|self|null>
     */
    public function getOnly(string|int ...$keys): array;

    /**
     * Get all the params except the specified ones.
     *
     * @param non-empty-string|int ...$keys The param names
     *
     * @return array<non-empty-string|int, scalar|self|null>
     */
    public function getAllExcept(string|int ...$keys): array;

    /**
     * Get a new instance with the specified params.
     *
     * @param array<non-empty-string|int, scalar|self|null> $params The params
     */
    public function with(array $params): static;

    /**
     * Get a new instance with the added params.
     *
     * @param array<non-empty-string|int, scalar|self|null> $params The params
     */
    public function withAdded(array $params): static;

    /**
     * Get the wire representation: a plain, recursively flattened JSON object.
     *
     * @return array<non-empty-string|int, scalar|array<array-key, mixed>|null>
     */
    public function asArray(): array;
}
