<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Payload;

use Override;
use Valkyrja\Queue\Message\Payload\Contract\PayloadContract;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidPayloadParamException;

use function array_filter;
use function in_array;
use function is_array;
use function is_scalar;

use const ARRAY_FILTER_USE_KEY;

class Payload implements PayloadContract
{
    /** @var array<non-empty-string|int, scalar|PayloadContract|null> */
    protected array $params = [];

    /**
     * @param array<non-empty-string|int, scalar|PayloadContract|null> $params The params
     */
    public function __construct(array $params = [])
    {
        $this->validateParams($params);

        $this->params = $params;
    }

    /**
     * Create a new instance from an array, recursively converting nested arrays.
     *
     * @param array<array-key, mixed> $data The data to create from
     */
    public static function fromArray(array $data): static
    {
        $params = [];

        /**
         * @var array-key                                           $name
         * @var scalar|object|array<array-key, mixed>|resource|null $param
         */
        foreach ($data as $name => $param) {
            if (is_array($param)) {
                $param = static::fromArray($param);
            }

            static::validateParam($param);

            $params[$name] = $param;
        }

        /**
         * @var array<non-empty-string|int, scalar|PayloadContract|null> $params
         *
         * @phpstan-ignore-next-line
         */
        return new static($params);
    }

    /**
     * Validate a param.
     *
     * @psalm-assert scalar|PayloadContract|null $param
     *
     * @phpstan-assert scalar|PayloadContract|null $param
     */
    protected static function validateParam(mixed $param): void
    {
        if (! static::isValidParam($param)) {
            throw new QueueMessageInvalidPayloadParamException(
                'Payload param must be scalar, null, or a PayloadContract instance'
            );
        }
    }

    /**
     * Determine if a param is valid.
     */
    protected static function isValidParam(mixed $param): bool
    {
        return is_scalar($param) || $param instanceof PayloadContract || $param === null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(int|string $key): bool
    {
        return isset($this->params[$key]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(int|string $key): PayloadContract|float|bool|int|string|null
    {
        return $this->params[$key]
            ?? null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAll(): array
    {
        return $this->params;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getOnly(string|int ...$keys): array
    {
        return array_filter(
            $this->params,
            static fn (string|int $name): bool => in_array($name, $keys, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAllExcept(string|int ...$keys): array
    {
        return array_filter(
            $this->params,
            static fn (string|int $name): bool => ! in_array($name, $keys, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function with(array $params): static
    {
        $this->validateParams($params);

        $new = clone $this;

        $new->params = $params;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAdded(array $params): static
    {
        $this->validateParams($params);

        $new = clone $this;

        // Do not use array_merge as it would rewrite int keys when mixed with string keys
        foreach ($params as $name => $param) {
            $new->params[$name] = $param;
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asArray(): array
    {
        $data = [];

        foreach ($this->params as $name => $param) {
            $data[$name] = $param instanceof PayloadContract
                ? $param->asArray()
                : $param;
        }

        return $data;
    }

    /**
     * Validate params.
     *
     * @param array<non-empty-string|int, mixed> $params The params to validate
     *
     * @psalm-assert array<non-empty-string|int, scalar|PayloadContract|null> $params
     *
     * @phpstan-assert array<non-empty-string|int, scalar|PayloadContract|null> $params
     */
    protected function validateParams(array $params): void
    {
        /**
         * @var scalar|object|array<array-key, mixed>|resource|null $param
         */
        foreach ($params as $param) {
            static::validateParam($param);
        }
    }
}
