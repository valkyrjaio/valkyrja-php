<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\Beanstalkd;

use JsonException;
use Override;
use Pheanstalk\Contract\PheanstalkManagerInterface;
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\Job;
use Pheanstalk\Values\TubeName;
use Valkyrja\Application\Entry\Abstract\PullQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Client\Data\Contract\QueueBeanstalkdClientConfigContract;
use Valkyrja\Queue\Client\Manager\BeanstalkdClient;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;

use function ceil;
use function max;
use function min;

class BeanstalkdQueue extends PullQueue
{
    /**
     * The tube every beanstalkd connection watches until it is told otherwise.
     *
     * @var non-empty-string
     */
    public const string DEFAULT_TUBE = 'default';

    /** @var int<0, max> The seconds a reserve blocks; 0 polls without blocking */
    protected static int $timeout = 1;

    protected static (PheanstalkManagerInterface&PheanstalkSubscriberInterface)|null $pheanstalk = null;

    /** @var non-empty-string */
    protected static string $tube = 'default';

    /**
     * The job currently reserved, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough, and
     * it is cleared on settlement so a second settle cannot act twice.
     */
    protected static Job|null $current = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        $config = $app->getContainer()->getSingleton(QueueBeanstalkdClientConfigContract::class);

        static::$tube       = $config->beanstalkdTube;
        static::$pheanstalk = static::getPheanstalk($config);

        $pheanstalk = static::getConnection();

        $pheanstalk->watch(new TubeName(static::$tube));

        // A fresh connection already watches `default`, and watching another
        // tube adds to that list rather than replacing it. Without the ignore,
        // a reserve can take a job another producer put on `default`.
        if (static::$tube !== self::DEFAULT_TUBE) {
            $pheanstalk->ignore(new TubeName(self::DEFAULT_TUBE));
        }
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public static function receive(): JobContract|null
    {
        $reserved = static::getConnection()->reserveWithTimeout(static::$timeout);

        if (! $reserved instanceof Job) {
            return null;
        }

        $job = static::decode($reserved);

        if ($job === null) {
            return null;
        }

        static::$current = $reserved;

        return static::withNormalizedAttempts($job, $reserved);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        // Anything still reserved was not completed, so hand it back rather
        // than letting it wait out the time-to-release
        static::releaseCurrent();

        static::getConnection()->disconnect();

        static::$pheanstalk = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $reserved = static::$current;

        if (! $reserved instanceof Job) {
            return;
        }

        static::$current = null;

        $pheanstalk = static::getConnection();

        if ($result === JobResult::RETRY) {
            // Release assigns a new priority and delay rather than keeping the
            // job's own, so both are passed: the default would drop every retry
            // to the least urgent priority and redeliver with no hold at all
            $pheanstalk->release($reserved, static::getPriority($job), static::getDelaySeconds($job));

            return;
        }

        if ($result->isDeadLettered()) {
            // Bury: beanstalkd keeps the job but stops delivering it, which is
            // the closest native equivalent of a dead-letter queue. A buried
            // job stays for inspection and can be kicked back onto the tube.
            $pheanstalk->bury($reserved);

            return;
        }

        $pheanstalk->delete($reserved);
    }

    /**
     * Read the envelope, and bury a body the factory cannot read.
     *
     * An unreadable body never reaches the handler, so nothing settles it. The
     * reserve would return the same bytes on the next cycle, and the worker
     * would fail on them again. beanstalkd has no redrive policy to end that
     * loop, so the entry buries the job itself.
     */
    protected static function decode(Job $reserved): JobContract|null
    {
        try {
            return new JobFactory()->fromJson($reserved->getData());
        } catch (JsonException|QueueMessageInvalidEnvelopeException) {
            static::getConnection()->bury($reserved);

            return null;
        }
    }

    /**
     * Read the reserve count back off the server and onto the job.
     *
     * A processor-owned adapter never rewrites the envelope, so the `attempts`
     * the producer published never advances on its own. beanstalkd counts the
     * reserves, and the adapter normalizes that count, which is what lets
     * `max_attempts` stop a failing chain. beanstalkd has no dead-letter policy
     * of its own, so burying at the ceiling is the framework's call and it
     * cannot make it without the count.
     */
    protected static function withNormalizedAttempts(JobContract $job, Job $reserved): JobContract
    {
        return $job->withAttempts(max(1, static::getConnection()->statsJob($reserved)->reserves));
    }

    /**
     * Invert the job's priority into beanstalkd's scale.
     *
     * beanstalkd treats 0 as the most urgent, so a higher envelope priority
     * becomes a lower beanstalkd one.
     *
     * @return int<0, max>
     */
    protected static function getPriority(JobContract $job): int
    {
        $priority = max(0, min($job->getPriority(), BeanstalkdClient::LOWEST_PRIORITY));

        return BeanstalkdClient::LOWEST_PRIORITY - $priority;
    }

    /**
     * Read the hold of the next attempt, in whole seconds.
     *
     * beanstalkd takes a delay in seconds, and the envelope holds milliseconds,
     * so a sub-second hold rounds up rather than down to no hold at all.
     *
     * @return int<0, max>
     */
    protected static function getDelaySeconds(JobContract $job): int
    {
        $milliseconds = $job->getRetryDelayForAttemptMs();

        return $milliseconds === 0
            ? 0
            : max(1, (int) ceil($milliseconds / 1000));
    }

    /**
     * Open the connection the loop reserves from.
     *
     * @codeCoverageIgnore A real beanstalkd server is unavailable in a test.
     */
    protected static function getPheanstalk(QueueBeanstalkdClientConfigContract $config): PheanstalkManagerInterface&PheanstalkSubscriberInterface
    {
        return Pheanstalk::create($config->beanstalkdHost, $config->beanstalkdPort);
    }

    /**
     * Get the connection that connect() opened.
     *
     * @throws QueueServerNotConnectedException
     */
    protected static function getConnection(): PheanstalkManagerInterface&PheanstalkSubscriberInterface
    {
        return static::$pheanstalk
            ?? throw new QueueServerNotConnectedException('The beanstalkd queue has no connection to reserve from.');
    }

    /**
     * Hand any reserved job back to the tube.
     */
    protected static function releaseCurrent(): void
    {
        $reserved = static::$current;

        if ($reserved instanceof Job) {
            static::$current = null;

            static::getConnection()->release($reserved);
        }
    }
}
