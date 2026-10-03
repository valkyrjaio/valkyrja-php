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
        // A blocking pop that timed out returns nothing, which is what lets the
        // entry's loop come back and check its bounds
        self::assertNull(RedisQueueFixture::receive());
    }

    public function testReceiveReturnsNullForAMalformedPop(): void
    {
        $this->redis->returns['blpop'] = ['only-the-key'];

        self::assertNull(RedisQueueFixture::receive());
    }

    public function testReceiveReturnsNullForANonStringValue(): void
    {
        $this->redis->returns['blpop'] = [self::QUEUE, 42];

        self::assertNull(RedisQueueFixture::receive());
    }

    public function testReceiveToleratesABodyThatIsNotJson(): void
    {
        $this->redis->returns['blpop'] = [self::QUEUE, 'not json at all'];

        // The pop already removed it, so throwing would lose the job and take
        // the worker down with it
        self::assertNull(RedisQueueFixture::receive());
    }

    public function testReceiveToleratesAnEnvelopeWithoutAName(): void
    {
        $this->redis->returns['blpop'] = [self::QUEUE, (string) json_encode(['attempts' => 2])];

        self::assertNull(RedisQueueFixture::receive());
    }

    public function testReceiveDecodesTheEnvelope(): void
    {
        $this->redis->returns['blpop'] = [
            self::QUEUE,
            (string) json_encode([EnvelopeField::NAME => 'SendWelcomeEmail', EnvelopeField::ATTEMPTS => 3]),
        ];

        $job = RedisQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame('SendWelcomeEmail', $job->getName());
        self::assertSame(3, $job->getAttempts());
    }

    public function testReceiveBlocksOnTheConfiguredQueueAndTimeout(): void
    {
        RedisQueueFixture::inject($this->redis, 'queues:emails', 5);

        RedisQueueFixture::receive();

        self::assertSame([['queues:emails'], 5], $this->redis->getCalls('blpop')[0]);
    }

    public function testReceivePromotesADueDelayedJob(): void
    {
        $envelope = (string) json_encode([EnvelopeField::NAME => 'SendWelcomeEmail']);

        $this->redis->returns['zrangebyscore'] = [$envelope];
        $this->redis->returns['zrem']          = 1;

        RedisQueueFixture::receive();

        self::assertSame(
            [[self::QUEUE . RedisClient::DELAYED_SUFFIX, '-inf', '1768564798000']],
            $this->redis->getCalls('zrangebyscore')
        );
        self::assertSame([[self::QUEUE, [$envelope]]], $this->redis->getCalls('rpush'));
    }

    public function testReceiveDoesNotPromoteWhenAnotherWorkerWonTheRemoval(): void
    {
        $this->redis->returns['zrangebyscore'] = [(string) json_encode([EnvelopeField::NAME => 'A'])];
        // A zero removal means another worker already claimed it
        $this->redis->returns['zrem'] = 0;

        RedisQueueFixture::receive();

        self::assertSame([], $this->redis->getCalls('rpush'));
    }

    public function testReceiveSkipsANonStringDelayedEntry(): void
    {
        $this->redis->returns['zrangebyscore'] = [42];
        $this->redis->returns['zrem']          = 1;

        RedisQueueFixture::receive();

        self::assertSame([], $this->redis->getCalls('zrem'));
        self::assertSame([], $this->redis->getCalls('rpush'));
    }

    public function testReceiveToleratesANonArrayDelayedResult(): void
    {
        $this->redis->returns['zrangebyscore'] = 'unexpected';

        self::assertNull(RedisQueueFixture::receive());
        self::assertSame([], $this->redis->getCalls('zrem'));
    }

    public function testNowFloorsAtZero(): void
    {
        Microtime::freeze(-1.0);

        RedisQueueFixture::receive();

        self::assertSame(
            [[self::QUEUE . RedisClient::DELAYED_SUFFIX, '-inf', '0']],
            $this->redis->getCalls('zrangebyscore')
        );
    }

    public function testARetryIsPublishedAgainBecauseRedisOwnsNoRetryLoop(): void
    {
        $client = new InMemoryClient();

        RedisQueueFixture::settle(new Job(name: 'SendWelcomeEmail', attempts: 1), JobResult::RETRY, $client);

        self::assertCount(1, $client->getPushed());
        self::assertSame(2, $client->getPushed()[0]->getAttempts());
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeNeedsNothingBecauseThePopRemovedTheJob(JobResult $result): void
    {
        $client = new InMemoryClient();

        RedisQueueFixture::settle(new Job(name: 'SendWelcomeEmail'), $result, $client);

        self::assertSame([], $client->getPushed());
    }

    protected function application(): ApplicationContract
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn(new QueueRedisClientConfig());

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        return $app;
    }
}
