<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Manager\Abstract;

use Override;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Support\Time\Microtime;

/**
 * Shared produce-side behavior: stamping the framework-owned fields and
 * recording what was handed over.
 *
 * Producing is a thin service straight over the adapter's publish — the entire
 * middleware pipeline runs on consume. Cross-cutting attributes are stamped as
 * producer-service defaults, not via a produce-side middleware stage.
 */
abstract class Client implements ClientContract
{
    /** @var non-empty-string */
    public const string LANGUAGE = 'php';

    /** @var JobContract[] */
    protected array $pushed = [];

    /**
     * @param non-empty-string $applicationName The application name stamped into the provenance
     * @param non-empty-string $version         The framework version stamped into the provenance
     */
    public function __construct(
        protected string $applicationName = 'valkyrja',
        protected string $version = ApplicationInfo::VERSION,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function push(JobContract $job): void
    {
        $stamped = $this->stamp($job);

        $this->pushed[] = $stamped;

        $this->publish($stamped);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function retry(JobContract $job, int $delayMs = 0): void
    {
        $this->pushed[] = $job;

        $this->republish($job, $delayMs);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPushed(): array
    {
        return $this->pushed;
    }

    /**
     * Hand an already incremented job back to the processor for redelivery.
     *
     * For a framework-owned processor this is essentially a publish of the
     * updated job, held for the given delay; a processor-owned one overrides it
     * to hand over the native retry signal instead, and ignores the delay
     * because the processor owns its own backoff.
     *
     * The in-process adapters skip the hold: there is no durable place to keep
     * a job waiting, so only the timing differs from production, never the
     * attempt count.
     *
     * @param int<0, max> $delayMs The hold before the job becomes eligible again
     */
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
        $this->publish($job);
    }

    /**
     * Stamp the fields the framework owns on a fresh enqueue.
     */
    protected function stamp(JobContract $job): JobContract
    {
        $now = $this->now();

        return $job
            ->withProducer($this->getProducer())
            ->withAttempts(1)
            ->withEnqueuedAtMs($now)
            ->withModifiedAtMs($now);
    }

    /**
     * Get the provenance, `AppName lang/version`.
     */
    protected function getProducer(): string
    {
        return $this->applicationName . ' ' . static::LANGUAGE . '/' . $this->version;
    }

    /**
     * Get the current time in epoch milliseconds.
     *
     * @return int<0, max>
     */
    protected function now(): int
    {
        $now = (int) (Microtime::get() * 1000.0);

        return $now > 0
            ? $now
            : 0;
    }

    /**
     * Hand a freshly stamped job to the processor.
     */
    abstract protected function publish(JobContract $job): void;
}
