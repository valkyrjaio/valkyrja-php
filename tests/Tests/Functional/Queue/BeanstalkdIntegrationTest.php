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
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\PullQueue;
use Valkyrja\Queue\Client\Manager\BeanstalkdClient;
use Valkyrja\Queue\Client\Puller\BeanstalkdPuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_string;
use function parse_url;

/**
 * Exercise the beanstalkd processor against a real server.
 *
 * beanstalkd owns redelivery through the reserve-and-release cycle, so a retry
 * is a release rather than a fresh publish. These tests prove that the server
 * redelivers a released job, that a deleted one is gone for good, and that a
 * dead-lettered one is buried rather than dropped — none of which a recording
 * double can tell you.
 */
final class BeanstalkdIntegrationTest extends TestCase
{
    /** @var non-empty-string */
    private const string TUBE = 'valkyrja-tests';

    private Pheanstalk $pheanstalk;

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

        $parts = parse_url($dsn);

        $this->pheanstalk = Pheanstalk::create(
            $parts['host'] ?? '127.0.0.1',
            $parts['port'] ?? 11300,
        );

        $this->drain();

        ResultLogMiddlewareFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->pheanstalk)) {
            $this->drain();
        }

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

        $puller = $this->puller();
        $puller->connect();

        $received = $puller->receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());

        $puller->settle($received, JobResult::ACK, $client);
    }

    public function testAnAcknowledgedJobIsGoneForGood(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame(0, $this->readyCount());
    }

    public function testAReleasedJobIsRedeliveredByTheServer(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        // Released back onto the tube — nothing was published
        self::assertSame(1, $this->readyCount());
        self::assertCount(1, $client->getPushed());
    }

    public function testADeadLetteredJobIsBuriedRatherThanDropped(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame([JobResult::FAIL], ResultLogMiddlewareFixture::getResults($job->getId()));
        // Off the ready tube, but kept for inspection rather than deleted
        self::assertSame(0, $this->readyCount());
        self::assertSame(1, $this->buriedCount());
    }

    public function testAnEmptyTubeYieldsNothing(): void
    {
        $puller = $this->puller();
        $puller->connect();

        self::assertNull($puller->receive());
    }

    private function client(): BeanstalkdClient
    {
        return new BeanstalkdClient(pheanstalk: $this->pheanstalk, tube: self::TUBE);
    }

    private function puller(): BeanstalkdPuller
    {
        return new BeanstalkdPuller(pheanstalk: $this->pheanstalk, tube: self::TUBE, timeout: 0);
    }

    private function config(): QueueConfigContract
    {
        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }

    private function readyCount(): int
    {
        return $this->stats()?->currentJobsReady ?? 0;
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

        while (($job = $this->pheanstalk->reserveWithTimeout(0)) !== null) {
            $this->pheanstalk->delete($job);
        }

        while (($buried = $this->pheanstalk->peekBuried()) !== null) {
            $this->pheanstalk->delete($buried);
        }
    }
}
