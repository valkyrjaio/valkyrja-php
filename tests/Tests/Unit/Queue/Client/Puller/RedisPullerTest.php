<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Puller;

use Override;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Client\Puller\RedisPuller;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Queue\Client\RedisFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_encode;

/**
 * Test the RedisPuller.
 */
final class RedisPullerTest extends TestCase
{
    /** @var non-empty-string */
    protected const string QUEUE = 'queues:default';

    protected RedisFixture $redis;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(1768564798.0);

        $this->redis = new RedisFixture();
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testConnectAndDisconnect(): void
    {
        $puller = $this->puller();

        $puller->connect();
        self::assertTrue($this->redis->connected);

        $puller->disconnect();
        self::assertFalse($this->redis->connected);
    }

    public function testReceiveReturnsNullOnATimeout(): void
    {
        // A blocking pop that timed out returns nothing, which is what lets the
        // entry's loop come back and check its bounds
        self::assertNull($this->puller()->receive());
    }

    public function testReceiveReturnsNullForAMalformedPop(): void
    {
        $this->redis->returns['blpop'] = ['only-the-key'];

        self::assertNull($this->puller()->receive());
    }

    public function testReceiveReturnsNullForANonStringValue(): void
    {
        $this->redis->returns['blpop'] = [self::QUEUE, 42];

        self::assertNull($this->puller()->receive());
    }

    public function testReceiveDecodesTheEnvelope(): void
    {
        $this->redis->returns['blpop'] = [
            self::QUEUE,
            (string) json_encode([EnvelopeField::NAME => 'SendWelcomeEmail', EnvelopeField::ATTEMPTS => 3]),
        ];

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame('SendWelcomeEmail', $job->getName());
        self::assertSame(3, $job->getAttempts());
    }

    public function testReceiveBlocksOnTheConfiguredQueueAndTimeout(): void
    {
        new RedisPuller(redis: $this->redis, queue: 'queues:emails', timeout: 5)->receive();

        $calls = $this->redis->getCalls('blpop');

        self::assertSame([['queues:emails'], 5], $calls[0]);
    }

    public function testReceivePromotesADueDelayedJob(): void
    {
        $envelope = (string) json_encode([EnvelopeField::NAME => 'SendWelcomeEmail']);

        $this->redis->returns['zrangebyscore'] = [$envelope];
        $this->redis->returns['zrem']          = 1;

        $this->puller()->receive();

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

        $this->puller()->receive();

        self::assertSame([], $this->redis->getCalls('rpush'));
    }

    public function testReceiveSkipsANonStringDelayedEntry(): void
    {
        $this->redis->returns['zrangebyscore'] = [42];
        $this->redis->returns['zrem']          = 1;

        $this->puller()->receive();

        self::assertSame([], $this->redis->getCalls('zrem'));
        self::assertSame([], $this->redis->getCalls('rpush'));
    }

    public function testReceiveToleratesANonArrayDelayedResult(): void
    {
        $this->redis->returns['zrangebyscore'] = 'unexpected';

        self::assertNull($this->puller()->receive());
        self::assertSame([], $this->redis->getCalls('zrem'));
    }

    public function testNowFloorsAtZero(): void
    {
        Microtime::freeze(-1.0);

        $this->puller()->receive();

        self::assertSame(
            [[self::QUEUE . RedisClient::DELAYED_SUFFIX, '-inf', '0']],
            $this->redis->getCalls('zrangebyscore')
        );
    }

    protected function puller(): RedisPuller
    {
        return new RedisPuller(redis: $this->redis, queue: self::QUEUE);
    }
}
