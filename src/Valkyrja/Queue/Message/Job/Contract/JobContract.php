<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Job\Contract;

use Valkyrja\Queue\Message\Attributes\Contract\AttributesContract;
use Valkyrja\Queue\Message\Payload\Contract\PayloadContract;

interface JobContract
{
    /**
     * Get the routing key — the router map key.
     *
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Get a new instance with the specified name.
     *
     * @param non-empty-string $name The name
     */
    public function withName(string $name): static;

    /**
     * Get the body.
     */
    public function getPayload(): PayloadContract;

    /**
     * Get a new instance with the specified payload.
     */
    public function withPayload(PayloadContract $payload): static;

    /**
     * Get the headers multi-map.
     */
    public function getAttributes(): AttributesContract;

    /**
     * Get a new instance with the specified attributes.
     */
    public function withAttributes(AttributesContract $attributes): static;

    /**
     * Get the provenance, `AppName lang/version`.
     */
    public function getProducer(): string;

    /**
     * Get a new instance with the specified producer.
     */
    public function withProducer(string $producer): static;

    /**
     * Get the identifier — a VLID V1, stable across retries.
     *
     * @return non-empty-string
     */
    public function getId(): string;

    /**
     * Get a new instance with the specified id.
     *
     * @param non-empty-string $id The id
     */
    public function withId(string $id): static;

    /**
     * Get the 1-based delivery count.
     *
     * @return positive-int
     */
    public function getAttempts(): int;

    /**
     * Get a new instance with the specified attempt count.
     *
     * @param positive-int $attempts The attempts
     */
    public function withAttempts(int $attempts): static;

    /**
     * Get the ceiling before dead-lettering.
     *
     * @return positive-int
     */
    public function getMaxAttempts(): int;

    /**
     * Get a new instance with the specified max attempts.
     *
     * @param positive-int $maxAttempts The max attempts
     */
    public function withMaxAttempts(int $maxAttempts): static;

    /**
     * Get the priority; higher runs sooner where the processor supports it.
     */
    public function getPriority(): int;

    /**
     * Get a new instance with the specified priority.
     */
    public function withPriority(int $priority): static;

    /**
     * Get the initial hold, in milliseconds, before the job is eligible.
     *
     * Producer-authored intent, applied on first enqueue only.
     *
     * @return int<0, max>
     */
    public function getDelayMs(): int;

    /**
     * Get a new instance with the specified delay.
     *
     * @param int<0, max> $delayMs The delay in milliseconds
     */
    public function withDelayMs(int $delayMs): static;

    /**
     * Get the hold, in milliseconds, before a retry re-enqueue.
     *
     * @return int<0, max>
     */
    public function getRetryDelayMs(): int;

    /**
     * Get a new instance with the specified retry delay.
     *
     * @param int<0, max> $retryDelayMs The retry delay in milliseconds
     */
    public function withRetryDelayMs(int $retryDelayMs): static;

    /**
     * Determine whether the retry hold ramps linearly with the attempt count.
     */
    public function getRetryDelayMultiplyByAttempt(): bool;

    /**
     * Get a new instance with the specified retry delay ramp.
     */
    public function withRetryDelayMultiplyByAttempt(bool $retryDelayMultiplyByAttempt): static;

    /**
     * Get the effective hold, in milliseconds, before this job's next retry.
     *
     * With the ramp set the hold is `retryDelayMs * attempts`, a linear ramp
     * that self-bounds through the max attempts ceiling; otherwise it is fixed,
     * with no ramp and no jitter.
     *
     * The count used is *this* job's, so a re-queue adapter reads it from the
     * dispatched job that just failed, never from the incremented copy it is
     * about to enqueue — reading the copy would make every hold one step too
     * long. With a delay of 1000 ms the holds are 1000, 2000, 3000 for
     * dispatched attempts of 1, 2, 3.
     *
     * @return int<0, max>
     */
    public function getRetryDelayForAttemptMs(): int;

    /**
     * Get the epoch milliseconds this job was first enqueued. Authoritative.
     *
     * @return int<0, max>
     */
    public function getEnqueuedAtMs(): int;

    /**
     * Get a new instance with the specified enqueue time.
     *
     * @param int<0, max> $enqueuedAtMs The epoch milliseconds
     */
    public function withEnqueuedAtMs(int $enqueuedAtMs): static;

    /**
     * Get the RFC 3339 rendering of the enqueue time. Informational only.
     *
     * @return non-empty-string
     */
    public function getEnqueuedAtIso(): string;

    /**
     * Get the epoch milliseconds the envelope was last rewritten. Authoritative.
     *
     * @return int<0, max>
     */
    public function getModifiedAtMs(): int;

    /**
     * Get a new instance with the specified modification time.
     *
     * @param int<0, max> $modifiedAtMs The epoch milliseconds
     */
    public function withModifiedAtMs(int $modifiedAtMs): static;

    /**
     * Get the RFC 3339 rendering of the modification time. Informational only.
     *
     * @return non-empty-string
     */
    public function getModifiedAtIso(): string;

    /**
     * Get the wire envelope.
     *
     * Every first-class field is written on every envelope, defaults included —
     * there is no omit-when-default, so a consumer never has to tell an absent
     * field from a defaulted one.
     *
     * @return array<non-empty-string, mixed>
     */
    public function asArray(): array;
}
