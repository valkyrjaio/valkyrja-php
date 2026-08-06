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
use Predis\Client;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\PullQueue;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Client\Puller\RedisPuller;
use Valkyrja\Queue\Client\Requeuer\Requeuer;
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
use function usleep;

/**
 * Exercise the Redis processor against a real server.
 *
 * The unit tests drive a recording double, which proves the adapter's own
 * branching but says nothing about whether the commands it issues are the right
 * ones. This is the only place the actual Redis semantics are checked: that a
 * pushed envelope round-trips byte-for-byte, that a delayed job is genuinely
 * withheld until it is due, and that a retry is held for its own delay rather
 * than the producer's.
 */
final class RedisIntegrationTest extends TestCase
{
    /** @var non-empty-string */
    private const string QUEUE = 'valkyrja:tests:queue';

    private Client $redis;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('REDIS_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Set REDIS_DSN to a reachable Redis server to run this test.');
        }

        if (! class_exists(Client::class)) {
            self::markTestSkipped('The predis/predis package is not installed.');
        }

        $this->redis = new Client($dsn);
        $this->redis->connect();

        $this->flush();

        ResultLogMiddlewareFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->redis)) {
            $this->flush();
            $this->redis->disconnect();
        }

        ResultLogMiddlewareFixture::reset();

        parent::tearDown();
    }

    public function testAPushedJobRoundTripsThroughRedisUnchanged(): void
    {
        $job = new Job(
            name: QueueRoutingProviderFixture::ALWAYS_ACK,
            payload: new JobFactory()->create('x', ['user_id' => 42, 'nested' => ['a' => 1]])->getPayload(),
            id: 'stable-id',
            maxAttempts: 7,
            priority: 3,
            retryDelayMs: 250,
            retryDelayMultiplyByAttempt: true,
        );

        $client = $this->client();
        $client->push($job);

        $received = $this->puller()->receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());
    }

    public function testAnImmediateJobIsAvailableAtOnce(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertSame(1, (int) $this->redis->llen(self::QUEUE));
        self::assertNotNull($this->puller()->receive());
    }

    public function testADelayedJobIsWithheldUntilItIsDue(): void
    {
        $this->client()->push(new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 60_000));

        // Held on the delayed set, not the ready list
        self::assertSame(0, (int) $this->redis->llen(self::QUEUE));
        self::assertSame(1, (int) $this->redis->zcard(self::QUEUE . RedisClient::DELAYED_SUFFIX));

        self::assertNull($this->puller()->receive());
    }

    public function testADueDelayedJobIsPromotedAndDelivered(): void
    {
        // A delay already elapsed by the time the puller looks
        $this->client()->push(new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 1));

        usleep(5_000);

        $received = $this->puller()->receive();

        self::assertNotNull($received);
        self::assertSame(QueueRoutingProviderFixture::ALWAYS_ACK, $received->getName());
        self::assertSame(0, (int) $this->redis->zcard(self::QUEUE . RedisClient::DELAYED_SUFFIX));
    }

    public function testARetryIsHeldForItsRetryDelayNotTheProducersDelay(): void
    {
        // The producer's delay is intent recorded at first publish; a retry is
        // timed by its own hold, so this must not wait a minute
        new Requeuer()->settle(
            new Job(
                name: QueueRoutingProviderFixture::ALWAYS_ACK,
                attempts: 2,
                delayMs: 60_000,
                retryDelayMs: 1,
            ),
            JobResult::RETRY,
            $this->client(),
        );

        usleep(5_000);

        $received = $this->puller()->receive();

        self::assertNotNull($received);
        self::assertSame(3, $received->getAttempts());
    }

    public function testAPullWorkerConsumesARealJobEndToEnd(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $this->puller(),
            client: $client,
            maxJobs: 1,
        );

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        // Acknowledged means consumed: nothing is left for another worker
        self::assertSame(0, (int) $this->redis->llen(self::QUEUE));
    }

    public function testAFailedJobIsNotReturnedToTheQueue(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $this->puller(),
            client: $client,
            maxJobs: 1,
        );

        self::assertSame([JobResult::FAIL], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame(0, (int) $this->redis->llen(self::QUEUE));
        self::assertSame(0, (int) $this->redis->zcard(self::QUEUE . RedisClient::DELAYED_SUFFIX));
    }

    public function testARetryingJobIsReEnqueuedWithAnIncrementedAttempt(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5, retryDelayMs: 1);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $this->puller(),
            client: $client,
            maxJobs: 1,
        );

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));

        usleep(5_000);

        $redelivered = $this->puller()->receive();

        self::assertNotNull($redelivered);
        // Same job, next attempt — the id is what makes that checkable
        self::assertSame($job->getId(), $redelivered->getId());
        self::assertSame(2, $redelivered->getAttempts());
    }

    private function client(): RedisClient
    {
        return new RedisClient(redis: $this->redis, queue: self::QUEUE);
    }

    private function puller(): RedisPuller
    {
        return new RedisPuller(redis: $this->redis, queue: self::QUEUE, timeout: 1);
    }

    private function config(): QueueConfigContract
    {
        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }

    private function flush(): void
    {
        $this->redis->del([self::QUEUE, self::QUEUE . RedisClient::DELAYED_SUFFIX]);
    }
}
