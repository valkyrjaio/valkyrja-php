<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry\Redis;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Application\Entry\Redis\RedisQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Data\QueueRedisClientConfig;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Application\Entry\RedisQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\RedisFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_encode;

final class RedisQueueTest extends TestCase
{
    /** @var non-empty-string */
    protected const string QUEUE = 'queues:default';

    /** The in-flight key of the default worker slot. */
    protected const string IN_FLIGHT = self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':default';

    protected RedisFixture $redis;

    /**
     * @return array<string, array{JobResult}>
     */
    public static function terminalProvider(): array
    {
        return [
            'ack'         => [JobResult::ACK],
            'fail'        => [JobResult::FAIL],
            'dead letter' => [JobResult::DEAD_LETTER],
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(1768564798.0);

        $this->redis = new RedisFixture();

        RedisQueueFixture::inject($this->redis, self::QUEUE);
    }

    #[Override]
    protected function tearDown(): void
    {
        RedisQueueFixture::reset();

        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testConnectReadsTheConfigAndConnects(): void
    {
        RedisQueueFixture::reset();
        RedisQueueFixture::inject($this->redis, 'queues:injected');

        RedisQueueFixture::connect($this->application());

        self::assertTrue($this->redis->connected);
    }

    public function testDisconnectClosesTheConnection(): void
    {
        RedisQueueFixture::connect($this->application());
        RedisQueueFixture::disconnect();

        self::assertFalse($this->redis->connected);
    }

    public function testPollingWithoutAConnectionFails(): void
    {
        RedisQueueFixture::reset();

        $this->expectException(QueueServerNotConnectedException::class);

        RedisQueueFixture::receive();
    }

    public function testReceiveReturnsNullOnATimeout(): void
    {
        // A blocking move that timed out returns nothing, which is what lets
        // the entry's loop come back and check its bounds
        self::assertNull(RedisQueueFixture::receive());
    }

    public function testReceiveReturnsNullForANonStringValue(): void
    {
        $this->redis->returns['blmove'] = 42;

        self::assertNull(RedisQueueFixture::receive());
    }

    public function testReceiveMovesTheEnvelopeOntoTheInFlightList(): void
    {
        RedisQueueFixture::inject($this->redis, 'queues:emails', 5);

        RedisQueueFixture::receive();

        // Taking the envelope off the ready list and putting it on the
        // in-flight list is one step, so a crash cannot lose it
        self::assertSame(
            [['queues:emails', 'queues:emails' . RedisQueue::IN_FLIGHT_SUFFIX . ':default', 'LEFT', 'RIGHT', 5]],
            $this->redis->getCalls('blmove')
        );
    }

    public function testReceiveParksABodyThatIsNotJson(): void
    {
        $this->redis->returns['blmove'] = 'not json at all';

        self::assertNull(RedisQueueFixture::receive());

        // A discard would retire the message while the worker kept reporting
        // healthy, so the envelope leaves a record instead
        self::assertSame(
            [[self::QUEUE . RedisQueue::UNREADABLE_SUFFIX, ['not json at all']]],
            $this->redis->getCalls('rpush')
        );
        self::assertSame(
            [[self::IN_FLIGHT, 1, 'not json at all']],
            $this->redis->getCalls('lrem')
        );
    }

    public function testReceiveParksAnEnvelopeWithoutAName(): void
    {
        $envelope = (string) json_encode(['attempts' => 2]);

        $this->redis->returns['blmove'] = $envelope;

        self::assertNull(RedisQueueFixture::receive());
        self::assertSame(
            [[self::QUEUE . RedisQueue::UNREADABLE_SUFFIX, [$envelope]]],
            $this->redis->getCalls('rpush')
        );
    }

    public function testReceiveDecodesTheEnvelope(): void
    {
        $this->redis->returns['blmove'] = (string) json_encode(
            [EnvelopeField::NAME => 'SendWelcomeEmail', EnvelopeField::ATTEMPTS => 3]
        );

        $job = RedisQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame('SendWelcomeEmail', $job->getName());
        self::assertSame(3, $job->getAttempts());
        // A readable envelope stays on the in-flight list until it settles
        self::assertSame([], $this->redis->getCalls('lrem'));
    }

    public function testReceivePromotesDueDelayedJobsInOneAtomicStep(): void
    {
        RedisQueueFixture::receive();

        // One script, so a worker that dies mid-promotion cannot leave a job on
        // neither key, and the limit stops a backlog stalling the poll
        self::assertSame(
            [[
                RedisQueueFixture::promoteScript(),
                2,
                self::QUEUE . RedisClient::DELAYED_SUFFIX,
                self::QUEUE,
                '1768564798000',
                '100',
            ]],
            $this->redis->getCalls('eval')
        );
    }

    public function testNowFloorsAtZero(): void
    {
        Microtime::freeze(-1.0);

        RedisQueueFixture::receive();

        self::assertSame('0', $this->redis->getCalls('eval')[0][4]);
    }

    public function testARetryIsPublishedAgainBecauseRedisOwnsNoRetryLoop(): void
    {
        $client   = new InMemoryClient();
        $envelope = (string) json_encode([EnvelopeField::NAME => 'SendWelcomeEmail']);

        RedisQueueFixture::holding($envelope);
        RedisQueueFixture::settle(new Job(name: 'SendWelcomeEmail', attempts: 1), JobResult::RETRY, $client);

        self::assertCount(1, $client->getPushed());
        self::assertSame(2, $client->getPushed()[0]->getAttempts());
        // The re-queue lands before the in-flight copy goes, so a crash between
        // them leaves a duplicate delivery rather than no delivery at all
        self::assertSame(
            [[self::IN_FLIGHT, 1, $envelope]],
            $this->redis->getCalls('lrem')
        );
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeDropsTheInFlightCopy(JobResult $result): void
    {
        $client   = new InMemoryClient();
        $envelope = (string) json_encode([EnvelopeField::NAME => 'SendWelcomeEmail']);

        RedisQueueFixture::holding($envelope);
        RedisQueueFixture::settle(new Job(name: 'SendWelcomeEmail'), $result, $client);

        self::assertSame([], $client->getPushed());
        self::assertSame(
            [[self::IN_FLIGHT, 1, $envelope]],
            $this->redis->getCalls('lrem')
        );
    }

    public function testSettlingWithNothingInFlightTouchesNoKey(): void
    {
        // A settle the entry did not receive for, such as a job the loop never
        // took off a list, has no in-flight copy to drop
        RedisQueueFixture::settle(new Job(name: 'SendWelcomeEmail'), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->redis->getCalls('lrem'));
    }

    public function testSettlingTwiceDropsTheInFlightCopyOnce(): void
    {
        $client = new InMemoryClient();

        RedisQueueFixture::holding('{"name":"SendWelcomeEmail"}');
        RedisQueueFixture::settle(new Job(name: 'SendWelcomeEmail'), JobResult::ACK, $client);
        RedisQueueFixture::settle(new Job(name: 'SendWelcomeEmail'), JobResult::ACK, $client);

        self::assertCount(1, $this->redis->getCalls('lrem'));
    }

    public function testDisconnectReturnsHeldEnvelopesToTheReadyList(): void
    {
        RedisQueueFixture::connect($this->application());
        $this->redis->calls = [];

        RedisQueueFixture::disconnect();

        // A graceful stop hands the slot's work back rather than leaving it for
        // the next start of this slot
        self::assertSame(
            [[RedisQueueFixture::reclaimScript(), 2, self::IN_FLIGHT, self::QUEUE, '1000']],
            $this->redis->getCalls('eval')
        );
        self::assertFalse($this->redis->connected);
    }

    public function testConnectReclaimsTheSlotsOwnHeldEnvelopes(): void
    {
        RedisQueueFixture::reset();
        RedisQueueFixture::inject($this->redis, self::QUEUE);

        // A slot the config names, not the default, so a hardcoded name fails
        RedisQueueFixture::connect($this->application(new QueueRedisClientConfig(
            redisQueue: self::QUEUE,
            redisWorkerName: 'worker.2',
        )));

        // The reclaim names this slot's key alone, so it cannot take an
        // envelope a live worker on another slot is running
        self::assertSame(
            [[
                RedisQueueFixture::reclaimScript(),
                2,
                self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':worker.2',
                self::QUEUE,
                '1000',
            ]],
            $this->redis->getCalls('eval')
        );
    }

    public function testEachWorkerSlotHoldsItsDeliveryOnItsOwnKey(): void
    {
        RedisQueueFixture::inject($this->redis, self::QUEUE, 1, 'worker.7');

        RedisQueueFixture::receive();

        self::assertSame(
            [[self::QUEUE, self::QUEUE . RedisQueue::IN_FLIGHT_SUFFIX . ':worker.7', 'LEFT', 'RIGHT', 1]],
            $this->redis->getCalls('blmove')
        );
    }

    protected function application(QueueRedisClientConfig|null $config = null): ApplicationContract
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn($config ?? new QueueRedisClientConfig());

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        return $app;
    }
}
