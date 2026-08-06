<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Manager;

use JsonException;
use Override;
use Predis\ClientInterface;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

/**
 * Publishes to Redis.
 *
 * Redis has no native retry, so this is a re-queue processor: the framework
 * owns redelivery and the envelope's attempt count and modification time are
 * authoritative. A delayed job goes onto a sorted set keyed by the instant it
 * becomes eligible; an immediate one goes straight onto the list.
 */
class RedisClient extends Client
{
    /** @var non-empty-string */
    public const string DELAYED_SUFFIX = ':delayed';

    /**
     * @param non-empty-string $queue           The list key jobs are pushed onto
     * @param non-empty-string $applicationName The application name stamped into the provenance
     * @param non-empty-string $version         The framework version stamped into the provenance
     */
    public function __construct(
        protected ClientInterface $redis,
        protected string $queue = 'queues:default',
        string $applicationName = 'valkyrja',
        string $version = ApplicationInfo::VERSION,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
        parent::__construct(
            applicationName: $applicationName,
            version: $version,
        );
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    protected function publish(JobContract $job): void
    {
        $delay = $job->getDelayMs();

        if ($delay > 0) {
            $this->publishDelayed($job, $delay);

            return;
        }

        $this->redis->rpush($this->queue, [$this->factory->toJson($job)]);
    }

    /**
     * @inheritDoc
     *
     * Redis has no native retry, so this is a re-queue processor: the hold comes
     * from the re-queuer, which keyed it to the attempt that just failed. The
     * producer's original delay is intent recorded at first publish and never
     * re-fires.
     *
     * @throws JsonException
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
        if ($delayMs > 0) {
            $this->publishDelayed($job, $delayMs);

            return;
        }

        $this->redis->rpush($this->queue, [$this->factory->toJson($job)]);
    }

    /**
     * Hold a job on the delayed set until it becomes eligible.
     *
     * @param int<0, max> $delayMs The hold in milliseconds
     *
     * @throws JsonException
     */
    protected function publishDelayed(JobContract $job, int $delayMs): void
    {
        $this->redis->zadd(
            $this->getDelayedQueue(),
            [$this->factory->toJson($job) => $this->now() + $delayMs]
        );
    }

    /**
     * Get the key of the set delayed jobs are held on.
     *
     * @return non-empty-string
     */
    protected function getDelayedQueue(): string
    {
        return $this->queue . static::DELAYED_SUFFIX;
    }
}
