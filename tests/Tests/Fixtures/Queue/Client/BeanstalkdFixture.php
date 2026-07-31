<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Client;

use Override;
use Pheanstalk\Contract\JobIdInterface;
use Pheanstalk\Contract\PheanstalkPublisherInterface;
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use Pheanstalk\Values\Job;
use Pheanstalk\Values\JobId;
use Pheanstalk\Values\TubeList;
use Pheanstalk\Values\TubeName;

/**
 * A recording stand-in for a beanstalkd connection.
 *
 * It answers both roles, because the adapter splits publishing and consuming
 * across two contracts that one connection satisfies.
 */
final class BeanstalkdFixture implements PheanstalkPublisherInterface, PheanstalkSubscriberInterface
{
    /** @var array<int, array{0: string, 1: array<int, mixed>}> */
    public array $calls = [];

    /** @var Job|null The job the next reserve returns */
    public Job|null $next = null;

    public bool $disconnected = false;

    /**
     * Get the arguments of every call to a connection method.
     *
     * @return array<int, array<int, mixed>>
     */
    public function getCalls(string $method): array
    {
        $calls = [];

        foreach ($this->calls as [$name, $arguments]) {
            if ($name === $method) {
                $calls[] = $arguments;
            }
        }

        return $calls;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function put(
        string $data,
        int $priority = self::DEFAULT_PRIORITY,
        int $delay = self::DEFAULT_DELAY,
        int $timeToRelease = self::DEFAULT_TTR
    ): JobIdInterface {
        $this->calls[] = ['put', [$data, $priority, $delay, $timeToRelease]];

        return new Job(new JobId(1), $data);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function useTube(TubeName $tube): void
    {
        $this->calls[] = ['useTube', [$tube->value]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function listTubeUsed(): TubeName
    {
        return new TubeName('default');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function watch(TubeName $tube): int
    {
        $this->calls[] = ['watch', [$tube->value]];

        return 1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reserveWithTimeout(int $timeout): Job|null
    {
        $this->calls[] = ['reserveWithTimeout', [$timeout]];

        $next = $this->next;

        $this->next = null;

        return $next;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function delete(JobIdInterface $job): void
    {
        $this->calls[] = ['delete', [$job->getId()]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function release(
        JobIdInterface $job,
        int $priority = PheanstalkPublisherInterface::DEFAULT_PRIORITY,
        int $delay = PheanstalkPublisherInterface::DEFAULT_DELAY
    ): void {
        $this->calls[] = ['release', [$job->getId(), $priority, $delay]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function bury(JobIdInterface $job, int $priority = PheanstalkPublisherInterface::DEFAULT_PRIORITY): void
    {
        $this->calls[] = ['bury', [$job->getId(), $priority]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        $this->calls[] = ['disconnect', []];

        $this->disconnected = true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reserve(): Job
    {
        return new Job(new JobId(1), '');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reserveJob(JobIdInterface $job): Job
    {
        return new Job(new JobId($job->getId()), '');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function touch(JobIdInterface $job): void
    {
        $this->calls[] = ['touch', [$job->getId()]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function ignore(TubeName $tube): int
    {
        $this->calls[] = ['ignore', [$tube->value]];

        return 1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function listTubesWatched(): TubeList
    {
        return new TubeList(new TubeName('default'));
    }
}
