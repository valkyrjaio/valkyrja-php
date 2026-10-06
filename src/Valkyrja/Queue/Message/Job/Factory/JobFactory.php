<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Job\Factory;

use JsonException;
use Override;
use stdClass;
use Valkyrja\Queue\Message\Attributes\Attributes;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Message\Payload\Contract\PayloadContract;
use Valkyrja\Queue\Message\Payload\Payload;
use Valkyrja\Queue\Message\Throwable\Exception\Abstract\QueueMessageInvalidArgumentException;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidPayloadParamException;
use Valkyrja\Type\Array\Factory\ArrayFactory;
use Valkyrja\Type\Array\Throwable\Exception\ArrayInvalidEncodedArrayException;

use function is_array;
use function is_bool;
use function is_int;
use function is_object;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

class JobFactory implements JobFactoryContract
{
    /**
     * Read the payload, from either shape the envelope can carry.
     *
     * `fromJson()` hands this the value decoded without associative arrays, so
     * a JSON object and a JSON array stay apart. A caller of `fromArray()` has
     * a PHP array, where an empty one cannot say which it was.
     *
     * @param array<array-key, mixed> $data The envelope
     */
    protected static function readPayload(array $data): PayloadContract
    {
        /** @var mixed $payload */
        $payload = $data[EnvelopeField::PAYLOAD] ?? [];

        if ($payload instanceof PayloadContract) {
            return $payload;
        }

        if (is_object($payload)) {
            return Payload::fromJsonValue($payload);
        }

        return Payload::fromArray(is_array($payload) ? $payload : []);
    }

    /**
     * Render a payload node for the wire.
     *
     * A nested map goes out as a JSON object and a nested list as a JSON array.
     * The encoder writes an empty PHP array as `[]`, and the keys cannot tell an
     * empty map from an empty list, so each node is asked rather than guessed at.
     *
     * The envelope's own `payload` field is always an object, whatever its keys
     * look like, because that is the shape the wire contract names.
     *
     * @param bool $isRoot Whether this is the envelope's payload field
     *
     * @return object|array<array-key, mixed>
     */
    protected static function renderPayload(PayloadContract $payload, bool $isRoot = false): object|array
    {
        $rendered = [];

        /** @var scalar|PayloadContract|null $value */
        foreach ($payload->getAll() as $key => $value) {
            $rendered[$key] = $value instanceof PayloadContract
                ? static::renderPayload($value)
                : $value;
        }

        return ! $isRoot && $payload->isList()
            ? $rendered
            : (object) $rendered;
    }

    /**
     * @inheritDoc
     *
     * @throws QueueMessageInvalidEnvelopeException
     * @throws QueueMessageInvalidPayloadParamException
     */
    #[Override]
    public function create(string $name, PayloadContract|array $payload = []): JobContract
    {
        return new Job(
            name: $name,
            payload: $payload instanceof PayloadContract
                ? $payload
                : Payload::fromArray($payload),
        );
    }

    /**
     * @inheritDoc
     *
     * @throws QueueMessageInvalidEnvelopeException
     */
    #[Override]
    public function fromArray(array $data): JobContract
    {
        $name = $data[EnvelopeField::NAME] ?? null;

        if (! is_string($name) || $name === '') {
            throw new QueueMessageInvalidEnvelopeException('Job envelope must carry a non-empty `name`');
        }

        $id = $this->readString($data, EnvelopeField::ID, '');

        try {
            $attributes = Attributes::fromArray($this->readArray($data, EnvelopeField::ATTRIBUTES));
            $payload    = static::readPayload($data);
        } catch (QueueMessageInvalidArgumentException $exception) {
            // A caller reads one wire body and declares one failure for it, so
            // a value it cannot accept reads as a bad envelope. The abstract
            // catches a bad attribute name, a bad attribute value, and a bad
            // payload param alike, so a new sibling cannot escape the declared
            // contract.
            throw new QueueMessageInvalidEnvelopeException(
                'Job envelope must carry readable attributes and payload',
                previous: $exception,
            );
        }

        return new Job(
            name: $name,
            payload: $payload,
            attributes: $attributes,
            id: $id !== ''
                ? $id
                : null,
            producer: $this->readString($data, EnvelopeField::PRODUCER, ''),
            attempts: $this->readPositiveInt($data, EnvelopeField::ATTEMPTS, 1),
            maxAttempts: $this->readPositiveInt($data, EnvelopeField::MAX_ATTEMPTS, Job::DEFAULT_MAX_ATTEMPTS),
            priority: $this->readInt($data, EnvelopeField::PRIORITY, 0),
            delayMs: $this->readUnsignedInt($data, EnvelopeField::DELAY_MS, 0),
            retryDelayMs: $this->readUnsignedInt($data, EnvelopeField::RETRY_DELAY_MS, Job::DEFAULT_RETRY_DELAY_MS),
            retryDelayMultiplyByAttempt: $this->readBool($data, EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT),
            enqueuedAtMs: $this->readOptionalUnsignedInt($data, EnvelopeField::ENQUEUED_AT_MS),
            modifiedAtMs: $this->readOptionalUnsignedInt($data, EnvelopeField::MODIFIED_AT_MS),
        );
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     * @throws QueueMessageInvalidEnvelopeException
     */
    #[Override]
    public function fromJson(string $json): JobContract
    {
        try {
            $data = ArrayFactory::fromString($json);
        } catch (ArrayInvalidEncodedArrayException $exception) {
            // Valid JSON that carries no object, such as `5` or `"text"`
            throw new QueueMessageInvalidEnvelopeException(
                'Job envelope must be a JSON object',
                previous: $exception,
            );
        }

        // The payload is read again without associative arrays, so a JSON object
        // and a JSON array stay apart. The decode above turns both into a PHP
        // array, and an empty map is then indistinguishable from an empty list.
        /** @var object|array<array-key, mixed>|scalar|null $raw */
        $raw = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        if ($raw instanceof stdClass && isset($raw->{EnvelopeField::PAYLOAD})) {
            /** @var mixed $payload */
            $payload = $raw->{EnvelopeField::PAYLOAD};

            if (is_object($payload) || is_array($payload)) {
                $data[EnvelopeField::PAYLOAD] = $payload;
            }
        }

        return $this->fromArray($data);
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function toJson(JobContract $job): string
    {
        $envelope = $job->asArray();

        // Both maps are objects on the wire, and json_encode writes an empty
        // PHP array as `[]`. A strict consumer in another language rejects that
        // for a map, so each map is cast rather than left to the encoder. The
        // cast is scoped to the two maps: JSON_FORCE_OBJECT would also rewrite
        // an attribute's value list into an object and break its `str -> [str]`
        // shape.
        $envelope[EnvelopeField::ATTRIBUTES] = (object) $envelope[EnvelopeField::ATTRIBUTES];
        $envelope[EnvelopeField::PAYLOAD]    = static::renderPayload($job->getPayload(), isRoot: true);

        return ArrayFactory::toString($envelope);
    }

    /**
     * Read an array field, defaulting to an empty one.
     *
     * @param array<array-key, mixed> $data  The decoded envelope
     * @param non-empty-string        $field The field name
     *
     * @return array<array-key, mixed>
     */
    protected function readArray(array $data, string $field): array
    {
        /** @var scalar|object|array<array-key, mixed>|resource|null $value */
        $value = $data[$field] ?? null;

        return is_array($value)
            ? $value
            : [];
    }

    /**
     * Read a string field, defaulting when a producer did not send it.
     *
     * @param array<array-key, mixed> $data    The decoded envelope
     * @param non-empty-string        $field   The field name
     * @param string                  $default The default
     */
    protected function readString(array $data, string $field, string $default): string
    {
        /** @var scalar|object|array<array-key, mixed>|resource|null $value */
        $value = $data[$field] ?? null;

        return is_string($value)
            ? $value
            : $default;
    }

    /**
     * Read a boolean field, defaulting to false.
     *
     * @param array<array-key, mixed> $data  The decoded envelope
     * @param non-empty-string        $field The field name
     */
    protected function readBool(array $data, string $field): bool
    {
        /** @var scalar|object|array<array-key, mixed>|resource|null $value */
        $value = $data[$field] ?? null;

        return is_bool($value) && $value;
    }

    /**
     * Read an integer field, defaulting when a producer did not send it.
     *
     * @param array<array-key, mixed> $data    The decoded envelope
     * @param non-empty-string        $field   The field name
     * @param int                     $default The default
     */
    protected function readInt(array $data, string $field, int $default): int
    {
        /** @var scalar|object|array<array-key, mixed>|resource|null $value */
        $value = $data[$field] ?? null;

        return is_int($value)
            ? $value
            : $default;
    }

    /**
     * Read a non-negative integer field, defaulting a missing or invalid value.
     *
     * @param array<array-key, mixed> $data    The decoded envelope
     * @param non-empty-string        $field   The field name
     * @param int<0, max>             $default The default
     *
     * @return int<0, max>
     */
    protected function readUnsignedInt(array $data, string $field, int $default): int
    {
        $value = $this->readInt($data, $field, $default);

        return $value >= 0
            ? $value
            : $default;
    }

    /**
     * Read a positive integer field, defaulting a missing or invalid value.
     *
     * @param array<array-key, mixed> $data    The decoded envelope
     * @param non-empty-string        $field   The field name
     * @param positive-int            $default The default
     *
     * @return positive-int
     */
    protected function readPositiveInt(array $data, string $field, int $default): int
    {
        $value = $this->readInt($data, $field, $default);

        return $value >= 1
            ? $value
            : $default;
    }

    /**
     * Read a non-negative integer field, returning null when absent.
     *
     * @param array<array-key, mixed> $data  The decoded envelope
     * @param non-empty-string        $field The field name
     *
     * @return int<0, max>|null
     */
    protected function readOptionalUnsignedInt(array $data, string $field): int|null
    {
        /** @var scalar|object|array<array-key, mixed>|resource|null $value */
        $value = $data[$field] ?? null;

        return is_int($value) && $value >= 0
            ? $value
            : null;
    }
}
