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
use Random\RandomException;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Type\Throwable\Exception\Abstract\TypeInvalidArgumentException;
use Valkyrja\Type\Throwable\Exception\Abstract\TypeRuntimeException;
use Valkyrja\Type\Vlid\Factory\VlidV1Factory;

class RedisClient extends Client
{
    /** @var non-empty-string */
    public const string DELAYED_SUFFIX = ':delayed';

    /**
     * What separates a delayed member's uniqueness token from its envelope.
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
     * @throws RandomException
     * @throws TypeInvalidArgumentException
     * @throws TypeRuntimeException
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
     * @throws RandomException
     * @throws TypeInvalidArgumentException
     * @throws TypeRuntimeException
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
     * @throws RandomException
     * @throws TypeInvalidArgumentException
     * @throws TypeRuntimeException
     */
    protected function enqueue(JobContract $job, int $delayMs): void
    {
        $encoded = $this->factory->toJson($job);

        if ($delayMs > 0) {
            // A sorted set holds one copy of a member, so the token is what
            // keeps two pushes of one job from scoring the same string
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
