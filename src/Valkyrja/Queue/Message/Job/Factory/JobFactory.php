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
use Valkyrja\Queue\Message\Attributes\Attributes;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Message\Payload\Contract\PayloadContract;
use Valkyrja\Queue\Message\Payload\Payload;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Type\Array\Factory\ArrayFactory;

use function is_array;
use function is_bool;
use function is_int;
use function is_string;

class JobFactory implements JobFactoryContract
{
    /**
     * @inheritDoc
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
     */
    #[Override]
    public function fromArray(array $data): JobContract
    {
        $name = $data[EnvelopeField::NAME] ?? null;

        if (! is_string($name) || $name === '') {
            throw new QueueMessageInvalidEnvelopeException('Job envelope must carry a non-empty `name`');
        }

        $id = $this->readString($data, EnvelopeField::ID, '');

        return new Job(
            name: $name,
            payload: Payload::fromArray($this->readArray($data, EnvelopeField::PAYLOAD)),
            attributes: Attributes::fromArray($this->readArray($data, EnvelopeField::ATTRIBUTES)),
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
     */
    #[Override]
    public function fromJson(string $json): JobContract
    {
        return $this->fromArray(ArrayFactory::fromString($json));
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function toJson(JobContract $job): string
    {
        return ArrayFactory::toString($job->asArray());
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
