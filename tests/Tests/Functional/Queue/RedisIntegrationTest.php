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
use Valkyrja\Application\Entry\Redis\RedisQueue;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Application\Data\RedisWorkerConfigFixture;
use Valkyrja\Tests\Fixtures\Application\Entry\RedisQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_int;
use function is_string;
use function parse_url;
use function usleep;

final class RedisIntegrationTest extends TestCase
{
    /** @var non-empty-string */
    private const string QUEUE = 'valkyrja:tests:queue';

    private Client $redis;

    /** @var non-empty-string */
    private string $redisHost = '127.0.0.1';

    private int $redisPort = 6379;

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

        $parts            = (array) parse_url($dsn);
        $this->redisHost  = is_string($parts['host'] ?? null) ? $parts['host'] : '127.0.0.1';
        $this->redisPort  = is_int($parts['port'] ?? null) ? $parts['port'] : 6379;

        RedisQueueFixture::inject($this->redis, self::QUEUE);

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

        RedisQueueFixture::reset();

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

        $received = RedisQueueFixture::receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());
    }

    public function testAnImmediateJobIsAvailableAtOnce(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertSame(1, (int) $this->redis->llen(self::QUEUE));
        self::assertNotNull(RedisQueueFixture::receive());
    }

    public function testADelayedJobIsWithheldUntilItIsDue(): void
    {
        $this->client()->push(new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 60_000));

        // Held on the delayed set, not the ready list
        self::assertSame(0, (int) $this->redis->llen(self::QUEUE));
        self::assertSame(1, (int) $this->redis->zcard(self::QUEUE . RedisClient::DELAYED_SUFFIX));

        self::assertNull(RedisQueueFixture::receive());
    }

    public function testADueDelayedJobIsPromotedAndDelivered(): void
    {
        // A delay already elapsed by the time the puller looks
        $this->client()->push(new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 1));

        usleep(5_000);

        $received = RedisQueueFixture::receive();

        self::assertNotNull($received);
        self::assertSame(QueueRoutingProviderFixture::ALWAYS_ACK, $received->getName());
        self::assertSame(0, (int) $this->redis->zcard(self::QUEUE . RedisClient::DELAYED_SUFFIX));
    }

    public function testARetryIsHeldForItsRetryDelayNotTheProducersDelay(): void
    {
        // The producer's delay is intent recorded at first publish; a retry is
        // timed by its own hold, so this must not wait a minute
        $this->client()->requeue(
            new Job(
                name: QueueRoutingProviderFixture::ALWAYS_ACK,
                attempts: 2,
                delayMs: 60_000,
                retryDelayMs: 1,
            ),
        );

        usleep(5_000);

        $received = RedisQueueFixture::receive();

        self::assertNotNull($received);
        self::assertSame(3, $received->getAttempts());
    }

    public function testAPullWorkerConsumesARealJobEndToEnd(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        RedisQueueFixture::run(
            config: $this->config(),
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

        RedisQueueFixture::run(
            config: $this->config(),
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

        RedisQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));

        usleep(5_000);

        // The loop disconnected on its way out, so poll on a fresh connection
        RedisQueueFixture::inject($this->redis, self::QUEUE);

        $redelivered = RedisQueueFixture::receive();

        self::assertNotNull($redelivered);
        // Same job, next attempt — the id is what makes that checkable
        self::assertSame($job->getId(), $redelivered->getId());
        self::assertSame(2, $redelivered->getAttempts());
    }

    public function testAReceivedJobSitsOnTheInFlightListUntilItSettles(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        $received = RedisQueueFixture::receive();

        self::assertNotNull($received);
        // The move took it off the ready list and put it on the in-flight list
        // in one step, so a crash here leaves the job on a key
        self::assertSame(0, (int) $this->redis->llen(self::QUEUE));
        self::assertSame(1, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX));

        RedisQueueFixture::settle($received, JobResult::ACK, $this->client());

        self::assertSame(0, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX));
    }

    public function testAWorkerRunLeavesNothingInFlight(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        RedisQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        self::assertSame(0, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX));
    }

    public function testAnUnreadableEnvelopeIsParkedRatherThanDiscarded(): void
    {
        $this->redis->rpush(self::QUEUE, ['not an envelope']);

        self::assertNull(RedisQueueFixture::receive());

        // A discard would retire the message while the worker kept reporting
        // healthy, so it lands where an operator can find it
        self::assertSame(1, (int) $this->redis->llen(self::QUEUE . RedisQueue::UNREADABLE_SUFFIX));
        self::assertSame(0, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX));
    }

    private function client(): RedisClient
    {
        return new RedisClient(redis: $this->redis, queue: self::QUEUE);
    }

    private function config(): QueueConfigContract
    {
        return new RedisWorkerConfigFixture(
            redisHost: $this->redisHost,
            redisPort: $this->redisPort,
            redisQueue: self::QUEUE,
        );
    }

    private function flush(): void
    {
        $this->redis->del([
            self::QUEUE,
            self::QUEUE . RedisClient::DELAYED_SUFFIX,
            self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX,
            self::QUEUE . RedisQueue::UNREADABLE_SUFFIX,
        ]);
    }
}
