<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Metadata;

use ArrayIterator;
use Override;
use Traversable;
use Valkyrja\Grpc\Message\Metadata\Contract\MetadataContract;
use Valkyrja\Grpc\Throwable\Exception\MetadataInvalidKeyException;
use Valkyrja\Grpc\Throwable\Exception\MetadataInvalidValueException;

use function preg_match;
use function str_ends_with;
use function strtolower;

class Metadata implements MetadataContract
{
    /** @var non-empty-string */
    protected const string BINARY_SUFFIX = '-bin';

    /**
     * The valid gRPC metadata key charset (matched against the normalized, lower-cased key): one or
     * more of a lowercase letter, digit, `-`, `_`, or `.`. Mirrors the set gRPC itself enforces, so
     * a key accepted here is one the transport will accept.
     *
     * @var non-empty-string
     */
    protected const string VALID_KEY_REGEX = '/^[a-z0-9._-]+$/';

    /**
     * The valid charset for an ASCII (non-`-bin`) metadata value: printable ASCII only, which is
     * what the wire permits for a non-binary header value.
     *
     * @var non-empty-string
     */
    protected const string VALID_ASCII_VALUE_REGEX = '/^[\x20-\x7e]*$/';

    /** @var array<string, string[]> */
    protected array $values = [];

    /**
     * @param array<string, string[]> $values The values
     */
    public function __construct(array $values = [])
    {
        $normalized = [];

        foreach ($values as $key => $keyValues) {
            $normalizedKey = self::normalize($key);

            self::validateKey($normalizedKey);

            foreach ($keyValues as $value) {
                self::validateValue($normalizedKey, $value);
            }

            $normalized[$normalizedKey] = $keyValues;
        }

        $this->values = $normalized;
    }

    /**
     * Reject a key that is not a valid gRPC header name at the point of insertion — as HTTP does
     * for header names — so a malformed key fails fast in the handler rather than surfacing as an
     * opaque transport error when the response is written.
     *
     * @param string $normalizedKey The already-normalized key
     *
     * @throws MetadataInvalidKeyException
     */
    protected static function validateKey(string $normalizedKey): void
    {
        if (preg_match(self::VALID_KEY_REGEX, $normalizedKey) !== 1) {
            throw new MetadataInvalidKeyException(
                "'$normalizedKey' is not a valid metadata key; keys may contain only lowercase letters, digits, '-', '_', and '.'."
            );
        }
    }

    /**
     * Enforce the metadata value kind at the boundary: a `-bin` key carries arbitrary bytes, every
     * other key carries printable ASCII. Validating on construction — the single point every
     * `with*` operation flows through — means the adapter can trust the values instead of a
     * non-ASCII byte surfacing as an opaque transport error deep inside the wire write.
     *
     * @param string $normalizedKey The already-normalized key
     * @param string $value         The value to validate
     *
     * @throws MetadataInvalidValueException
     */
    protected static function validateValue(string $normalizedKey, string $value): void
    {
        if (str_ends_with($normalizedKey, self::BINARY_SUFFIX)) {
            return;
        }

        if (preg_match(self::VALID_ASCII_VALUE_REGEX, $value) !== 1) {
            throw new MetadataInvalidValueException(
                "ASCII metadata key '$normalizedKey' requires a printable ASCII value; use a '-bin' suffixed key to carry binary values."
            );
        }
    }

    /**
     * Normalize a key to its case-insensitive form.
     */
    protected static function normalize(string $key): string
    {
        return strtolower($key);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(string $key): string|null
    {
        return $this->values[self::normalize($key)][0]
            ?? null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAll(string $key): array
    {
        return $this->values[self::normalize($key)]
            ?? [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $key): bool
    {
        return isset($this->values[self::normalize($key)]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isBinaryKey(string $key): bool
    {
        return str_ends_with(self::normalize($key), self::BINARY_SUFFIX);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function with(string $key, string $value): static
    {
        $values = $this->values;

        $values[self::normalize($key)] = [$value];

        return new static($values);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAdded(string $key, string $value): static
    {
        $values        = $this->values;
        $normalizedKey = self::normalize($key);

        $values[$normalizedKey][] = $value;

        return new static($values);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function without(string $key): static
    {
        $values = $this->values;

        unset($values[self::normalize($key)]);

        return new static($values);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @inheritDoc
     *
     * @return Traversable<string, string[]>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }
}
