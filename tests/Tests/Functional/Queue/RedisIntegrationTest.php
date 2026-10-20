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
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Message\Payload\Payload;
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

    public function testTwoPushesOfOneDelayedJobBothSurvive(): void
    {
        // A sorted set keeps one copy of a member. The envelope used whole as
        // the member made two pushes inside one millisecond score the same
        // string, and the second push silently replaced the first.
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 1);

        $this->client()->push($job);
        $this->client()->push($job);

        self::assertSame(2, (int) $this->redis->zcard(self::QUEUE . RedisClient::DELAYED_SUFFIX));

        usleep(5_000);

        self::assertNotNull(RedisQueueFixture::receive());
        self::assertNotNull(RedisQueueFixture::receive());
    }

    public function testADelayedMemberWrittenBeforeTheTokenStillPromotes(): void
    {
        // A deployment carries jobs that an older client already wrote with no
        // token, and the promote script has to hand those on unchanged
        $envelope = new JobFactory()->toJson(
            new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK)
        );

        $this->redis->zadd(self::QUEUE . RedisClient::DELAYED_SUFFIX, [$envelope => 1]);

        $received = RedisQueueFixture::receive();

        self::assertNotNull($received);
        self::assertSame(QueueRoutingProviderFixture::ALWAYS_ACK, $received->getName());
    }

    public function testATokenlessMemberHoldingTheSeparatorPromotesWhole(): void
    {
        // The separator is legal inside an envelope, so a pattern that read
        // the token as everything before the first one would cut this member
        // down to a fragment and park it as unreadable
        $envelope = new JobFactory()->toJson(
            new Job(
                name: QueueRoutingProviderFixture::ALWAYS_ACK,
                payload: new Payload(['label' => 'Report|Daily'])
            )
        );

        self::assertStringContainsString(RedisClient::DELAYED_SEPARATOR, $envelope);

        $this->redis->zadd(self::QUEUE . RedisClient::DELAYED_SUFFIX, [$envelope => 1]);

        $received = RedisQueueFixture::receive();

        self::assertNotNull($received);
        self::assertSame(QueueRoutingProviderFixture::ALWAYS_ACK, $received->getName());
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
        self::assertSame(1, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':default'));

        RedisQueueFixture::settle($received, JobResult::ACK, $this->client());

        self::assertSame(0, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':default'));
    }

    public function testAWorkerRunLeavesNothingInFlight(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        RedisQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        self::assertSame(0, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':default'));
    }

    public function testASlotTakesItsOwnHeldEnvelopeBackOnConnect(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertNotNull(RedisQueueFixture::receive());
        self::assertSame(1, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':default'));

        // A worker killed here never settled, so the next start of this slot
        // takes the envelope back rather than leaving it for a human
        RedisQueueFixture::reset();
        RedisQueueFixture::inject($this->redis, self::QUEUE);
        RedisQueueFixture::connect($this->application());

        self::assertSame(0, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':default'));
        self::assertSame(1, (int) $this->redis->llen(self::QUEUE));
    }

    public function testAReclaimReturnsAHeldEnvelopeToTheTailRatherThanTheHead(): void
    {
        $held = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);
        $this->client()->push($held);

        self::assertNotNull(RedisQueueFixture::receive());

        // A second job reaches the ready list while the first is still held
        $waiting = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);
        $this->client()->push($waiting);

        RedisQueueFixture::reset();
        RedisQueueFixture::inject($this->redis, self::QUEUE);
        RedisQueueFixture::connect($this->application());

        /** @var string[] $ready */
        $ready = $this->redis->lrange(self::QUEUE, 0, -1);

        self::assertCount(2, $ready);

        $factory = new JobFactory();

        // The reclaim goes onto the tail, so the job that was already waiting
        // keeps its place: at the head a job that kills its worker every time
        // would hold up everything behind it
        self::assertSame($waiting->getId(), $factory->fromJson($ready[0])->getId());
        self::assertSame($held->getId(), $factory->fromJson($ready[1])->getId());
    }

    public function testAnUnreadableEnvelopeIsParkedRatherThanDiscarded(): void
    {
        $this->redis->rpush(self::QUEUE, ['not an envelope']);

        self::assertNull(RedisQueueFixture::receive());

        // A discard would retire the message while the worker kept reporting
        // healthy, so it lands where an operator can find it
        self::assertSame(1, (int) $this->redis->llen(self::QUEUE . RedisQueue::UNREADABLE_SUFFIX));
        self::assertSame(0, (int) $this->redis->llen(self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':default'));
    }

    private function client(): RedisClient
    {
        return new RedisClient(redis: $this->redis, queue: self::QUEUE);
    }

    private function config(): QueueConfigContract
    {
        return new RedisWorkerConfigFixture(
            queueRedisClientHost: $this->redisHost,
            queueRedisClientPort: $this->redisPort,
            queueRedisClientQueue: self::QUEUE,
        );
    }

    private function application(): ApplicationContract
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn($this->config());

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        return $app;
    }

    private function flush(): void
    {
        $this->redis->del([
            self::QUEUE,
            self::QUEUE . RedisClient::DELAYED_SUFFIX,
            self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':default',
            self::QUEUE . RedisQueue::UNREADABLE_SUFFIX,
        ]);
    }
}
