<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Manager;

use Override;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Queue\Client\RedisFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function array_key_first;
use function explode;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class RedisClientTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string QUEUE = 'queues:default';

    /** @var int<0, max> */
    protected const int FROZEN_MS = 1768564798000;

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

    public function testPushWritesTheEnvelopeOntoTheReadyList(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $calls = $this->redis->getCalls('rpush');

        self::assertCount(1, $calls);
        self::assertSame(self::QUEUE, $calls[0][0]);

        /** @var array<string, mixed> $envelope */
        $envelope = json_decode($calls[0][1][0], true);

        self::assertSame(self::NAME, $envelope[EnvelopeField::NAME]);
        self::assertSame(['user_id' => 42], $envelope[EnvelopeField::PAYLOAD]);
        self::assertSame(1, $envelope[EnvelopeField::ATTEMPTS]);
    }

    public function testPushHoldsADelayedJobOnTheDelayedSet(): void
    {
        $this->client()->push(new Job(name: self::NAME, delayMs: 5000));

        self::assertSame([], $this->redis->getCalls('rpush'));

        $calls = $this->redis->getCalls('zadd');

        self::assertCount(1, $calls);
        self::assertSame(self::QUEUE . RedisClient::DELAYED_SUFFIX, $calls[0][0]);
        // Scored by the instant it becomes eligible
        self::assertSame(self::FROZEN_MS + 5000, $calls[0][1][array_key_first($calls[0][1])]);
    }

    public function testTwoPushesOfOneJobHoldTwoDelayedMembers(): void
    {
        // A sorted set keeps one copy of a member, and the clock is frozen
        // here, so an envelope used as the member whole would score the same
        // string twice and deliver one of the two jobs
        $job = new Job(name: self::NAME, delayMs: 5000);

        $this->client()->push($job);
        $this->client()->push($job);

        $calls = $this->redis->getCalls('zadd');

        self::assertCount(2, $calls);
        self::assertNotSame(
            array_key_first($calls[0][1]),
            array_key_first($calls[1][1])
        );
    }

    public function testADelayedMemberCarriesTheEnvelopeAfterItsToken(): void
    {
        // The promote script hands the ready list everything past the first
        // separator, so that remainder has to be the envelope exactly
        $this->client()->push(new Job(name: self::NAME, delayMs: 5000));

        $member = array_key_first($this->redis->getCalls('zadd')[0][1]);

        [$token, $envelope] = explode(RedisClient::DELAYED_SEPARATOR, $member, 2);

        self::assertNotSame('', $token);
        self::assertSame(
            self::NAME,
            json_decode($envelope, true, flags: JSON_THROW_ON_ERROR)[EnvelopeField::NAME]
        );
    }

    public function testRetryHoldsTheJobUntilTheSuppliedDelayElapses(): void
    {
        $job = new Job(name: self::NAME, attempts: 2);

        $this->client()->retry($job, 250);

        $calls = $this->redis->getCalls('zadd');

        self::assertCount(1, $calls);
        self::assertSame(self::FROZEN_MS + 250, $calls[0][1][array_key_first($calls[0][1])]);
    }

    public function testRetryAppliesTheSuppliedHoldRatherThanDerivingOne(): void
    {
        // requeue() derives the hold and hands it over, so retry() must apply
        // what it was given — deriving from these would give 750, and the
        // producer's delay is intent at first publish that must not re-fire
        $job = new Job(
            name: self::NAME,
            attempts: 3,
            delayMs: 60_000,
            retryDelayMs: 250,
            retryDelayMultiplyByAttempt: true,
        );

        $this->client()->retry($job, 250);

        $calls = $this->redis->getCalls('zadd');

        self::assertSame(self::FROZEN_MS + 250, $calls[0][1][array_key_first($calls[0][1])]);
    }

    public function testRetryWithNoHoldGoesStraightOntoTheReadyList(): void
    {
        $job = new Job(name: self::NAME, attempts: 2, retryDelayMs: 0);

        $this->client()->retry($job, 0);

        self::assertCount(1, $this->redis->getCalls('rpush'));
        self::assertSame([], $this->redis->getCalls('zadd'));
    }

    public function testRetryPreservesTheIdAndAttemptCount(): void
    {
        $job = new Job(name: self::NAME, id: 'stable-id', attempts: 4, retryDelayMs: 0);

        $this->client()->retry($job, 0);

        /** @var array<string, mixed> $envelope */
        $envelope = json_decode($this->redis->getCalls('rpush')[0][1][0], true);

        self::assertSame('stable-id', $envelope[EnvelopeField::ID]);
        self::assertSame(4, $envelope[EnvelopeField::ATTEMPTS]);
    }

    public function testUsesTheConfiguredQueueKey(): void
    {
        new RedisClient(redis: $this->redis, queue: 'queues:emails')->push(new JobFactory()->create(self::NAME));

        self::assertSame('queues:emails', $this->redis->getCalls('rpush')[0][0]);
    }

    protected function client(): RedisClient
    {
        return new RedisClient(redis: $this->redis, queue: self::QUEUE);
    }
}
