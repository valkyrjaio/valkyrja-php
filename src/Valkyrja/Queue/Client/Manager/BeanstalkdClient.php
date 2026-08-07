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
use Pheanstalk\Contract\PheanstalkPublisherInterface;
use Pheanstalk\Values\TubeName;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

use function intdiv;
use function max;
use function min;

class BeanstalkdClient extends Client
{
    /**
     * The least urgent beanstalkd priority, and the width of the range.
     *
     * beanstalkd takes an unsigned 32-bit priority, but the envelope's range is
     * far smaller, so the client maps onto the low end and leaves the rest.
     */
    public const int LOWEST_PRIORITY = 1024;

    /** The seconds a reserved job may run before beanstalkd releases it. */
    public const int DEFAULT_TIME_TO_RELEASE = 60;

    /**
     * @param non-empty-string $tube            The tube jobs are published to
     * @param non-empty-string $applicationName The application name stamped into the provenance
     * @param non-empty-string $version         The framework version stamped into the provenance
     */
    public function __construct(
        protected PheanstalkPublisherInterface $pheanstalk,
        protected string $tube = 'default',
        protected int $timeToRelease = self::DEFAULT_TIME_TO_RELEASE,
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
        $this->pheanstalk->useTube(new TubeName($this->tube));

        $this->pheanstalk->put(
            $this->factory->toJson($job),
            $this->getPriority($job),
            $this->getDelaySeconds($job->getDelayMs()),
            $this->timeToRelease,
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
    }

    /**
     * Invert the job's priority into beanstalkd's scale.
     *
     * beanstalkd treats 0 as the most urgent, so a higher envelope priority
     * must map to a lower beanstalkd number.
     *
     * @return int<0, 1024>
     */
    protected function getPriority(JobContract $job): int
    {
        $priority = max(0, min($job->getPriority(), self::LOWEST_PRIORITY));

        return self::LOWEST_PRIORITY - $priority;
    }

    /**
     * Convert a hold in milliseconds to the whole seconds beanstalkd takes.
     *
     * A sub-second hold rounds down to zero, because beanstalkd has no finer
     * grain.
     *
     * @param int<0, max> $delayMs The hold in milliseconds
     *
     * @return int<0, max>
     */
    protected function getDelaySeconds(int $delayMs): int
    {
        return max(0, intdiv($delayMs, 1000));
    }
}
