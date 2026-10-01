<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Functional\Queue;

use Override;
use Pheanstalk\Exception\TubeNotFoundException;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;
use Pheanstalk\Values\TubeStats;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Entry\Beanstalkd\BeanstalkdQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Manager\BeanstalkdClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Application\Data\BeanstalkdWorkerConfigFixture;
use Valkyrja\Tests\Fixtures\Application\Entry\BeanstalkdQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_int;
use function is_string;
use function parse_url;

final class BeanstalkdIntegrationTest extends TestCase
{
    /** @var non-empty-string */
    private const string TUBE = 'valkyrja-tests';

    private Pheanstalk $pheanstalk;

    /** @var non-empty-string */
    private string $host = '127.0.0.1';

    private int $port = 11300;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('BEANSTALKD_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Set BEANSTALKD_DSN to a reachable beanstalkd server to run this test.');
        }

        if (! class_exists(Pheanstalk::class)) {
            self::markTestSkipped('The pda/pheanstalk package is not installed.');
        }

        $parts = (array) parse_url($dsn);

        $this->host = is_string($parts['host'] ?? null) && $parts['host'] !== '' ? $parts['host'] : '127.0.0.1';
        $this->port = is_int($parts['port'] ?? null) ? $parts['port'] : 11300;

        $this->pheanstalk = Pheanstalk::create($this->host, $this->port);

        $this->drain();

        BeanstalkdQueueFixture::inject($this->pheanstalk, self::TUBE, timeout: 0);

        ResultLogMiddlewareFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->pheanstalk)) {
            $this->drain();
        }

        BeanstalkdQueueFixture::reset();

        ResultLogMiddlewareFixture::reset();

        parent::tearDown();
    }

    public function testAPublishedJobRoundTripsThroughTheServerUnchanged(): void
    {
        $job = new Job(
            name: QueueRoutingProviderFixture::ALWAYS_ACK,
            payload: new JobFactory()->create('x', ['user_id' => 42, 'nested' => ['a' => 1]])->getPayload(),
            id: 'stable-id',
            maxAttempts: 7,
            priority: 3,
        );

        $client = $this->client();
        $client->push($job);

        BeanstalkdQueueFixture::connect($this->application());

        $received = BeanstalkdQueueFixture::receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());

        BeanstalkdQueueFixture::settle($received, JobResult::ACK, $client);
    }

    public function testAnAcknowledgedJobIsGoneForGood(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        BeanstalkdQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame(0, $this->readyCount());
    }

    public function testAReleasedJobIsRedeliveredByTheServer(): void
    {
        // A zero hold releases the job straight back onto the ready list
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5, retryDelayMs: 0);

        $client = $this->client();
        $client->push($job);

        BeanstalkdQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        // Released back onto the tube — nothing was published
        self::assertSame(1, $this->readyCount());
        self::assertCount(1, $client->getPushed());
    }

    public function testAReleasedJobIsHeldForItsRetryDelay(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5, retryDelayMs: 60_000);

        $this->client()->push($job);

        BeanstalkdQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        // A release assigns the hold, so the retry is paced rather than a tight
        // loop that re-reserves the job the instant it is let go
        self::assertSame(0, $this->readyCount());
        self::assertSame(1, $this->delayedCount());
    }

    public function testAFailingJobIsBuriedAtTheAttemptCeiling(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 3, retryDelayMs: 0);

        $this->client()->push($job);

        // beanstalkd counts the reserves, so the ceiling is reachable. The
        // bound is the number of deliveries, not of jobs: a worker waiting for
        // a fourth delivery that never comes would never return.
        BeanstalkdQueueFixture::run(
            config: $this->config(),
            maxJobs: 3,
        );

        self::assertSame(
            [JobResult::RETRY, JobResult::RETRY, JobResult::DEAD_LETTER],
            ResultLogMiddlewareFixture::getResults($job->getId())
        );
        self::assertSame(0, $this->readyCount());
        self::assertSame(1, $this->buriedCount());
    }

    public function testADeadLetteredJobIsBuriedRatherThanDropped(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        $client = $this->client();
        $client->push($job);

        BeanstalkdQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        self::assertSame([JobResult::FAIL], ResultLogMiddlewareFixture::getResults($job->getId()));
        // Off the ready tube, but kept for inspection rather than deleted
        self::assertSame(0, $this->readyCount());
        self::assertSame(1, $this->buriedCount());
    }

    public function testAnEmptyTubeYieldsNothing(): void
    {
        BeanstalkdQueueFixture::connect($this->application());

        self::assertNull(BeanstalkdQueueFixture::receive());
    }

    /**
     * Build an application whose container carries the beanstalkd client config.
     */
    private function application(): ApplicationContract
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn($this->config());

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        return $app;
    }

    private function client(): BeanstalkdClient
    {
        return new BeanstalkdClient(pheanstalk: $this->pheanstalk, tube: self::TUBE);
    }

    private function config(): QueueConfigContract
    {
        return new BeanstalkdWorkerConfigFixture(
            beanstalkdHost: $this->host,
            beanstalkdPort: $this->port,
            beanstalkdTube: self::TUBE,
        );
    }

    private function readyCount(): int
    {
        return $this->stats()?->currentJobsReady ?? 0;
    }

    private function delayedCount(): int
    {
        return $this->stats()?->currentJobsDelayed ?? 0;
    }

    private function buriedCount(): int
    {
        return $this->stats()?->currentJobsBuried ?? 0;
    }

    /**
     * Get the tube's stats, or null when beanstalkd has dropped the tube.
     *
     * beanstalkd removes a tube once it holds no jobs and nobody watches it, so
     * a missing tube means an empty one rather than an error.
     */
    private function stats(): TubeStats|null
    {
        try {
            return $this->pheanstalk->statsTube(new TubeName(self::TUBE));
        } catch (TubeNotFoundException) {
            return null;
        }
    }

    /**
     * Take every job off the tube, so one test cannot see another's leftovers.
     */
    private function drain(): void
    {
        $tube = new TubeName(self::TUBE);

        $this->pheanstalk->watch($tube);
        $this->pheanstalk->useTube($tube);

        // A fresh connection watches `default` as well, and a reserve would then
        // take a job another producer put there
        if (self::TUBE !== BeanstalkdQueue::DEFAULT_TUBE) {
            $this->pheanstalk->ignore(new TubeName(BeanstalkdQueue::DEFAULT_TUBE));
        }

        while (($job = $this->pheanstalk->reserveWithTimeout(0)) !== null) {
            $this->pheanstalk->delete($job);
        }

        while (($buried = $this->pheanstalk->peekBuried()) !== null) {
            $this->pheanstalk->delete($buried);
        }

        // A reserve never sees a delayed job, so a held retry would otherwise
        // outlive the test that produced it and be counted by the next one
        while (($delayed = $this->pheanstalk->peekDelayed()) !== null) {
            $this->pheanstalk->delete($delayed);
        }
    }
}
