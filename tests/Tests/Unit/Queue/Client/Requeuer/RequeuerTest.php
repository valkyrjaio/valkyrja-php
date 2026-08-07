<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Requeuer;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Requeuer\Requeuer;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Queue\Client\RecordingClientFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class RequeuerTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

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
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeHandsNothingBack(JobResult $result): void
    {
        $client = new InMemoryClient();

        new Requeuer()->settle(new Job(name: self::NAME), $result, $client);

        self::assertSame([], $client->getPushed());
    }

    public function testARetryHandsBackAnIncrementedJob(): void
    {
        $client = new InMemoryClient();
        $job    = new Job(name: self::NAME, id: 'stable-id', attempts: 2, enqueuedAtMs: 5, modifiedAtMs: 5);

        new Requeuer()->settle($job, JobResult::RETRY, $client);

        $requeued = $client->getPushed()[0];

        self::assertSame(3, $requeued->getAttempts());
        // The id is stable across retries; only the modification time moves
        self::assertSame('stable-id', $requeued->getId());
        self::assertSame(5, $requeued->getEnqueuedAtMs());
        self::assertSame(1768564798000, $requeued->getModifiedAtMs());
    }

    public function testTheHoldIsKeyedToTheDispatchedAttemptNotTheIncrementedCopy(): void
    {
        $client = new RecordingClientFixture();

        // Dispatched at attempt 1: the hold is one delay, even though the copy
        // handed back carries attempt 2. Reading the copy would give 2000.
        new Requeuer()->settle(
            new Job(name: self::NAME, attempts: 1, retryDelayMs: 1000, retryDelayMultiplyByAttempt: true),
            JobResult::RETRY,
            $client
        );

        self::assertSame([1000], $client->delays);
        self::assertSame(2, $client->getPushed()[0]->getAttempts());
    }

    public function testTheHoldRampsWithEachDispatchedAttempt(): void
    {
        $client = new RecordingClientFixture();
        $job    = new Job(name: self::NAME, retryDelayMs: 1000, retryDelayMultiplyByAttempt: true);

        foreach ([1, 2, 3] as $attempts) {
            new Requeuer()->settle($job->withAttempts($attempts), JobResult::RETRY, $client);
        }

        self::assertSame([1000, 2000, 3000], $client->delays);
    }

    public function testTheHoldIsFixedWhenTheRampIsOff(): void
    {
        $client = new RecordingClientFixture();
        $job    = new Job(name: self::NAME, retryDelayMs: 1000);

        foreach ([1, 2, 3] as $attempts) {
            new Requeuer()->settle($job->withAttempts($attempts), JobResult::RETRY, $client);
        }

        // No ramp and no jitter — the same hold every time
        self::assertSame([1000, 1000, 1000], $client->delays);
    }

    public function testTheProducersDelayIsNeverReAppliedOnARetry(): void
    {
        $client = new RecordingClientFixture();

        new Requeuer()->settle(
            new Job(name: self::NAME, attempts: 1, delayMs: 60_000, retryDelayMs: 250),
            JobResult::RETRY,
            $client
        );

        // delay_ms is producer intent applied at first publish only
        self::assertSame([250], $client->delays);
    }

    public function testARetryLeavesTheOriginalJobUntouched(): void
    {
        $job = new Job(name: self::NAME, attempts: 2);

        new Requeuer()->settle($job, JobResult::RETRY, new InMemoryClient());

        self::assertSame(2, $job->getAttempts());
    }

    public function testNowFloorsAtZero(): void
    {
        Microtime::freeze(-1.0);

        $client = new InMemoryClient();

        new Requeuer()->settle(new Job(name: self::NAME), JobResult::RETRY, $client);

        self::assertSame(0, $client->getPushed()[0]->getModifiedAtMs());
    }
}
