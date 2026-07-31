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

namespace Valkyrja\Queue\Client\Puller;

use JsonException;
use Override;
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use Pheanstalk\Values\Job;
use Pheanstalk\Values\TubeName;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Puller\Contract\PullerContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

/**
 * Consumes from beanstalkd, and settles with it.
 *
 * This is both the puller and the re-queuer, for the same reason the AMQP
 * adapter is: settling means acting on the reserved job, and only the thing
 * that reserved it holds the job id. The framework never sees a job id — it
 * hands over an outcome enum and this translates it.
 *
 * beanstalkd needs no separate wait. A reserve blocks for the timeout and then
 * returns nothing, so an empty tube already yields the process.
 */
class BeanstalkdPuller implements PullerContract, RequeuerContract
{
    /**
     * The job currently reserved, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough — and
     * it is cleared on settlement so a second settle cannot act twice.
     */
    protected Job|null $current = null;

    /**
     * @param non-empty-string $tube    The tube jobs are consumed from
     * @param int<0, max>      $timeout The seconds a reserve blocks; 0 polls without blocking
     */
    public function __construct(
        protected PheanstalkSubscriberInterface $pheanstalk,
        protected string $tube = 'default',
        protected int $timeout = 1,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function connect(): void
    {
        $this->pheanstalk->watch(new TubeName($this->tube));
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function receive(): JobContract|null
    {
        $reserved = $this->pheanstalk->reserveWithTimeout($this->timeout);

        if (! $reserved instanceof Job) {
            return null;
        }

        $this->current = $reserved;

        return $this->factory->fromJson($reserved->getData());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        // Anything still reserved was not completed, so hand it back rather
        // than letting it wait out the time-to-release
        $this->releaseCurrent();

        $this->pheanstalk->disconnect();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $reserved = $this->current;

        if (! $reserved instanceof Job) {
            return;
        }

        $this->current = null;

        if ($result === JobResult::RETRY) {
            // Release: beanstalkd puts it back on the tube and redelivers
            $this->pheanstalk->release($reserved);

            return;
        }

        if ($result->isDeadLettered()) {
            // Bury: beanstalkd keeps the job but stops delivering it, which is
            // the closest native equivalent of a dead-letter queue. A buried
            // job stays for inspection and can be kicked back onto the tube.
            $this->pheanstalk->bury($reserved);

            return;
        }

        $this->pheanstalk->delete($reserved);
    }

    /**
     * Hand any reserved job back to the tube.
     */
    protected function releaseCurrent(): void
    {
        $reserved = $this->current;

        if ($reserved instanceof Job) {
            $this->current = null;

            $this->pheanstalk->release($reserved);
        }
    }
}
