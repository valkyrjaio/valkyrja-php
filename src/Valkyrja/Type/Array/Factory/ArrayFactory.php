<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Type\Array\Factory;

use ArrayAccess;
use JsonException;
use Stringable;
use Valkyrja\Type\Array\Throwable\Exception\ArrayInvalidEncodedArrayException;
use Valkyrja\Type\Array\Throwable\Exception\ArrayInvalidStringKeysException;

use function array_any;
use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class ArrayFactory
{
    /**
     * Get a subject value by dot notation key.
     *
     * @param ArrayAccess<string, mixed>|array<string, mixed> $subject      The subject to search
     * @param string                                          $key          The dot notation to search for
     * @param mixed|null                                      $defaultValue [optional] The default value
     * @param non-empty-string                                $separator    [optional] The separator
     *
     * @phpstan-param ArrayAccess<string, mixed>|array<string, mixed> $subject      The subject
     */
    public static function getValueDotNotation(
        ArrayAccess|array $subject,
        string $key,
        mixed $defaultValue = null,
        string $separator = '.'
    ): mixed {
        $value    = $subject;
        $keyParts = explode($separator, $key);

        // Explode the keys on period and iterate through the keys
        foreach ($keyParts as $item) {
            if (! is_array($value) && ! $value instanceof ArrayAccess) {
                return $defaultValue;
            }

            /** @var scalar|object|array<array-key, mixed>|resource|null $value */
            // Trying to get the item from the current value or set the default
            $value = $value[$item] ?? null;

            // If the value is ull then the dot notation doesn't exist in this array so return the default
            if ($value === null) {
                return $defaultValue;
            }
        }

        return $value;
    }

    /**
     * Convert an array to a string.
     *
     * @param array<array-key, mixed> $subject The subject array
     *
     * @throws JsonException
     *
     * @return non-empty-string
     */
    public static function toString(array $subject): string
    {
        return json_encode($subject, JSON_THROW_ON_ERROR);
    }

    /**
     * Un-convert an array from a string.
     *
     * @param string $subject The subject array as a string
     *
     * @throws JsonException
     * @throws ArrayInvalidEncodedArrayException
     *
     * @return array<array-key, mixed>
     */
    public static function fromString(string $subject): array
    {
        /** @var scalar|array<array-key, mixed>|bool|null $decoded */
        $decoded = json_decode($subject, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new ArrayInvalidEncodedArrayException("Invalid json string provided: `$subject`");
        }

        return $decoded;
    }

    /**
     * Strip out null valued properties in an array.
     *
     * @param array<array-key, mixed> $subject The subject array
     *
     * @return array<array-key, mixed>
     */
    public static function newWithoutNull(array $subject): array
    {
        $new = $subject;

        return static::withoutNull($new);
    }

    /**
     * Strip out null valued properties in an array.
     *
     * @param array<array-key, mixed> $subject The subject array
     *
     * @return array<array-key, mixed>
     */
    public static function withoutNull(array &$subject): array
    {
        /**
         * @var array-key                                           $key
         * @var scalar|object|array<array-key, mixed>|resource|null $value
         */
        foreach ($subject as $key => &$value) {
            if (is_array($value)) {
                static::withoutNull($value);
            }

            if ($value === null) {
                unset($subject[$key]);
            }
        }

        return $subject;
    }

    /**
     * Filter empty strings.
     *
     * @return array<Stringable|string>
     */
    public static function filterEmptyStrings(Stringable|string ...$strings): array
    {
        return array_filter($strings, static fn (Stringable|string $string): bool => ((string) $string) !== '');
    }

    /**
     * @throws JsonException
     *
     * @return array<array-key, mixed>
     */
    public static function fromMixed(mixed $value): array
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        return (array) $value;
    }

    /**
     * Validate an array's keys are strings.
     *
     * @param array<array-key, mixed> $array The array
     *
     * @psalm-assert array<string, mixed> $array
     *
     * @phpstan-assert array<string, mixed> $array
     */
    public static function validateKeysAreStrings(array $array): void
    {
        if (! static::determineIfKeysAreStrings($array)) {
            throw new ArrayInvalidStringKeysException('Array keys must be strings.');
        }
    }

    /**
     * Determine if an array's keys are strings.
     *
     * @param array<array-key, mixed> $array The array
     */
    public static function determineIfKeysAreStrings(array $array): bool
    {
        return ! array_any(array_keys($array), static fn (string|int $key): bool => ! is_string($key));
    }

    /**
     * Ensure an array's keys are strings.
     *
     * @param array<array-key, mixed> $array The array
     *
     * @return array<string, mixed>
     */
    public static function ensureKeysAreStrings(array $array): array
    {
        return array_combine(array_map(static fn (string|int $key): string => (string) $key, array_keys($array)), $array);
    }
}
