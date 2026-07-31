<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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

/**
 * Publishes to beanstalkd.
 *
 * beanstalkd owns redelivery through the reserve-and-release cycle, so this is
 * a processor-owned adapter: a retry is a release on the consumer side rather
 * than a fresh publish, which is why `republish` here is deliberately not a
 * publish.
 *
 * beanstalkd counts priority the other way round from the envelope: 0 is the
 * most urgent. The client inverts the job's priority so a higher number means
 * more urgent on both sides.
 */
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
     *
     * The reserve-and-release cycle owns redelivery, so a retry is signalled by
     * the consumer releasing the job rather than by publishing it again.
     * Publishing here would duplicate it: the original job is still reserved.
     * The hold is ignored for the same reason — beanstalkd owns its own
     * backoff, so the framework's ramp does not apply to a processor-owned
     * adapter.
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
