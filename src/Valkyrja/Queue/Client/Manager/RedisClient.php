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
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Type\Vlid\Factory\VlidV1Factory;

class RedisClient extends Client
{
    /** @var non-empty-string */
    public const string DELAYED_SUFFIX = ':delayed';

    /**
     * What separates a delayed member's uniqueness token from its envelope.
     *
     * An envelope is JSON, so it opens with a brace and carries no token of
     * its own. The first occurrence therefore ends the token, whatever the
     * payload holds after it.
     *
     * @var non-empty-string
     */
    public const string DELAYED_SEPARATOR = '|';

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
        $this->enqueue($job, $job->getDelayMs());
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs): void
    {
        $this->enqueue($job, $delayMs);
    }

    /**
     * Put a job on the ready list, or on the delayed set when it has a hold.
     *
     * @param int<0, max> $delayMs The hold in milliseconds
     *
     * @throws JsonException
     */
    protected function enqueue(JobContract $job, int $delayMs): void
    {
        $encoded = $this->factory->toJson($job);

        if ($delayMs > 0) {
            // A sorted set holds one copy of a member, and an envelope is the
            // whole member here, so two pushes of one job in a single
            // millisecond would score the same member twice and deliver once.
            // The token makes every member distinct, and the promote script
            // takes it back off.
            $member = VlidV1Factory::generate() . static::DELAYED_SEPARATOR . $encoded;

            $this->redis->zadd($this->getDelayedQueue(), [$member => Microtime::getMilliseconds() + $delayMs]);

            return;
        }

        $this->redis->rpush($this->queue, [$encoded]);
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
