<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Job;

use Override;
use Valkyrja\Queue\Message\Attributes\Attributes;
use Valkyrja\Queue\Message\Attributes\Contract\AttributesContract;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Payload\Contract\PayloadContract;
use Valkyrja\Queue\Message\Payload\Payload;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Support\Time\Rfc3339;
use Valkyrja\Type\Vlid\Factory\VlidV1Factory;

class Job implements JobContract
{
    /** @var positive-int */
    public const int DEFAULT_MAX_ATTEMPTS = 5;

    /** @var int<0, max> */
    public const int DEFAULT_RETRY_DELAY_MS = 1000;

    /** @var non-empty-string */
    protected string $id;

    /** @var int<0, max> */
    protected int $enqueuedAtMs;

    /** @var int<0, max> */
    protected int $modifiedAtMs;

    protected PayloadContract $payload;

    protected AttributesContract $attributes;

    /**
     * @param non-empty-string        $name                        The routing key
     * @param PayloadContract|null    $payload                     The body
     * @param AttributesContract|null $attributes                  The headers multi-map
     * @param non-empty-string|null   $id                          The VLID V1, generated when not supplied
     * @param string                  $producer                    The provenance
     * @param positive-int            $attempts                    The 1-based delivery count
     * @param positive-int            $maxAttempts                 The ceiling before dead-lettering
     * @param int                     $priority                    The priority
     * @param int<0, max>             $delayMs                     The initial hold before eligibility
     * @param int<0, max>             $retryDelayMs                The hold before a retry re-enqueue
     * @param bool                    $retryDelayMultiplyByAttempt Whether the retry hold ramps with the attempt
     * @param int<0, max>|null        $enqueuedAtMs                The epoch milliseconds first enqueued
     * @param int<0, max>|null        $modifiedAtMs                The epoch milliseconds last rewritten
     */
    public function __construct(
        protected string $name,
        PayloadContract|null $payload = null,
        AttributesContract|null $attributes = null,
        string|null $id = null,
        protected string $producer = '',
        protected int $attempts = 1,
        protected int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        protected int $priority = 0,
        protected int $delayMs = 0,
        protected int $retryDelayMs = self::DEFAULT_RETRY_DELAY_MS,
        protected bool $retryDelayMultiplyByAttempt = false,
        int|null $enqueuedAtMs = null,
        int|null $modifiedAtMs = null,
    ) {
        $this->validateName($name);
        $this->validateAttempts($attempts, $maxAttempts);
        $this->validateDelays($delayMs, $retryDelayMs);

        $enqueuedAt = $enqueuedAtMs ?? Microtime::now();

        /** @var non-empty-string $identifier */
        $identifier = $id ?? VlidV1Factory::generate();

        $this->id           = $identifier;
        $this->payload      = $payload ?? new Payload();
        $this->attributes   = $attributes ?? new Attributes();
        $this->enqueuedAtMs = $enqueuedAt;
        $this->modifiedAtMs = $modifiedAtMs ?? $enqueuedAt;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withName(string $name): static
    {
        $this->validateName($name);

        $new = clone $this;

        $new->name = $name;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPayload(): PayloadContract
    {
        return $this->payload;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withPayload(PayloadContract $payload): static
    {
        $new = clone $this;

        $new->payload = $payload;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAttributes(): AttributesContract
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAttributes(AttributesContract $attributes): static
    {
        $new = clone $this;

        $new->attributes = $attributes;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getProducer(): string
    {
        return $this->producer;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withProducer(string $producer): static
    {
        $new = clone $this;

        $new->producer = $producer;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withId(string $id): static
    {
        $this->validateId($id);

        $new = clone $this;

        $new->id = $id;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAttempts(int $attempts): static
    {
        $this->validateAttempts($attempts, $this->maxAttempts);

        $new = clone $this;

        $new->attempts = $attempts;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withMaxAttempts(int $maxAttempts): static
    {
        $this->validateAttempts($this->attempts, $maxAttempts);

        $new = clone $this;

        $new->maxAttempts = $maxAttempts;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withPriority(int $priority): static
    {
        $new = clone $this;

        $new->priority = $priority;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getDelayMs(): int
    {
        return $this->delayMs;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withDelayMs(int $delayMs): static
    {
        $this->validateDelays($delayMs, $this->retryDelayMs);

        $new = clone $this;

        $new->delayMs = $delayMs;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRetryDelayMs(): int
    {
        return $this->retryDelayMs;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withRetryDelayMs(int $retryDelayMs): static
    {
        $this->validateDelays($this->delayMs, $retryDelayMs);

        $new = clone $this;

        $new->retryDelayMs = $retryDelayMs;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRetryDelayMultiplyByAttempt(): bool
    {
        return $this->retryDelayMultiplyByAttempt;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withRetryDelayMultiplyByAttempt(bool $retryDelayMultiplyByAttempt): static
    {
        $new = clone $this;

        $new->retryDelayMultiplyByAttempt = $retryDelayMultiplyByAttempt;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRetryDelayForAttemptMs(): int
    {
        if (! $this->retryDelayMultiplyByAttempt) {
            return $this->retryDelayMs;
        }

        return $this->retryDelayMs * $this->attempts;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEnqueuedAtMs(): int
    {
        return $this->enqueuedAtMs;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withEnqueuedAtMs(int $enqueuedAtMs): static
    {
        $this->validateTimestamp($enqueuedAtMs);

        $new = clone $this;

        $new->enqueuedAtMs = $enqueuedAtMs;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEnqueuedAtIso(): string
    {
        return Rfc3339::fromMilliseconds($this->enqueuedAtMs);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getModifiedAtMs(): int
    {
        return $this->modifiedAtMs;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withModifiedAtMs(int $modifiedAtMs): static
    {
        $this->validateTimestamp($modifiedAtMs);

        $new = clone $this;

        $new->modifiedAtMs = $modifiedAtMs;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getModifiedAtIso(): string
    {
        return Rfc3339::fromMilliseconds($this->modifiedAtMs);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asArray(): array
    {
        return [
            EnvelopeField::ID                              => $this->id,
            EnvelopeField::NAME                            => $this->name,
            EnvelopeField::PRODUCER                        => $this->producer,
            EnvelopeField::ATTRIBUTES                      => $this->attributes->asArray(),
            EnvelopeField::ATTEMPTS                        => $this->attempts,
            EnvelopeField::MAX_ATTEMPTS                    => $this->maxAttempts,
            EnvelopeField::PRIORITY                        => $this->priority,
            EnvelopeField::DELAY_MS                        => $this->delayMs,
            EnvelopeField::RETRY_DELAY_MS                  => $this->retryDelayMs,
            EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT => $this->retryDelayMultiplyByAttempt,
            EnvelopeField::ENQUEUED_AT_MS                  => $this->enqueuedAtMs,
            EnvelopeField::ENQUEUED_AT_ISO                 => $this->getEnqueuedAtIso(),
            EnvelopeField::MODIFIED_AT_MS                  => $this->modifiedAtMs,
            EnvelopeField::MODIFIED_AT_ISO                 => $this->getModifiedAtIso(),
            EnvelopeField::PAYLOAD                         => $this->payload->asArray(),
        ];
    }

    /**
     * Validate the routing key.
     *
     * @psalm-assert non-empty-string $name
     *
     * @phpstan-assert non-empty-string $name
     */
    protected function validateName(string $name): void
    {
        if ($name === '') {
            throw new QueueMessageInvalidEnvelopeException('Job name must not be empty');
        }
    }

    /**
     * Validate the identifier.
     *
     * @psalm-assert non-empty-string $id
     *
     * @phpstan-assert non-empty-string $id
     */
    protected function validateId(string $id): void
    {
        if ($id === '') {
            throw new QueueMessageInvalidEnvelopeException('Job id must not be empty');
        }
    }

    /**
     * Validate the attempt counters.
     */
    protected function validateAttempts(int $attempts, int $maxAttempts): void
    {
        if ($attempts < 1) {
            throw new QueueMessageInvalidEnvelopeException('Job attempts must be at least 1');
        }

        if ($maxAttempts < 1) {
            throw new QueueMessageInvalidEnvelopeException('Job max attempts must be at least 1');
        }
    }

    /**
     * Validate the holds.
     */
    protected function validateDelays(int $delayMs, int $retryDelayMs): void
    {
        if ($delayMs < 0) {
            throw new QueueMessageInvalidEnvelopeException('Job delay must not be negative');
        }

        if ($retryDelayMs < 0) {
            throw new QueueMessageInvalidEnvelopeException('Job retry delay must not be negative');
        }
    }

    /**
     * Validate a timestamp.
     */
    protected function validateTimestamp(int $milliseconds): void
    {
        if ($milliseconds < 0) {
            throw new QueueMessageInvalidEnvelopeException('Job timestamps must not be negative');
        }
    }
}
