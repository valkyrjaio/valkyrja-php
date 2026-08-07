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
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Puller\DatabasePuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Queue\Client\DatabaseManagerFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\RecordingClientFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class DatabasePullerTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string QUEUE = 'default';

    /** @var int<0, max> */
    protected const int FROZEN_MS = 1768564798000;

    protected const int ROW_ID = 12;

    protected DatabaseManagerFixture $manager;

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

        $this->manager = new DatabaseManagerFixture();
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testAnEmptyTableYieldsNothing(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testTheSelectSkipsHeldAndReservedRows(): void
    {
        $this->puller()->receive();

        $select = $this->manager->getStatements('SELECT')[0];

        self::assertStringContainsString('reserved_at_ms IS NULL', $select->query);
        self::assertStringContainsString('available_at_ms <= :now', $select->query);
        self::assertStringContainsString('ORDER BY priority DESC, id ASC', $select->query);
        self::assertSame(self::QUEUE, $select->bound['queue']);
        self::assertSame(self::FROZEN_MS, $select->bound['now']);
    }

    public function testARowWithNoEnvelopeIsSkipped(): void
    {
        // A row the adapter cannot read is not one it may claim
        $this->manager->rows = [['id' => self::ROW_ID]];

        self::assertNull($this->puller()->receive());
        self::assertSame([], $this->manager->getStatements('UPDATE'));
    }

    public function testARowWithNoIdIsSkipped(): void
    {
        $this->manager->rows = [['envelope' => new JobFactory()->toJson(new JobFactory()->create(self::NAME))]];

        self::assertNull($this->puller()->receive());
        self::assertSame([], $this->manager->getStatements('UPDATE'));
    }

    public function testAStringIdIsReadBackAsAJob(): void
    {
        // PDO returns a BIGINT as a string on pgsql, and on mysql whenever the
        // driver emulates prepares, so the id arrives as text rather than an int
        $this->manager->rows = [
            [
                'id'       => (string) self::ROW_ID,
                'envelope' => new JobFactory()->toJson(new JobFactory()->create(self::NAME)),
            ],
        ];

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(self::ROW_ID, $this->manager->getStatements('UPDATE')[0]->bound['id']);
    }

    public function testAStaleReservationBecomesEligibleAgain(): void
    {
        // A worker that dies between the claim and the settle leaves the row
        // reserved. Without the staleness window no worker could ever take it.
        $this->puller()->receive();

        $select = $this->manager->getStatements('SELECT')[0];

        self::assertStringContainsString('reserved_at_ms IS NULL OR reserved_at_ms <= :stale', $select->query);
        self::assertSame(
            self::FROZEN_MS - DatabasePuller::DEFAULT_RESERVATION_TIMEOUT_MS,
            $select->bound['stale']
        );
    }

    public function testAClaimCanTakeAStaleReservation(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $this->puller()->receive();

        $update = $this->manager->getStatements('UPDATE')[0];

        // The claim must accept the same window the select offered, or a stale
        // row would be selected forever and never actually taken
        self::assertStringContainsString('reserved_at_ms IS NULL OR reserved_at_ms <= :stale', $update->query);
        self::assertSame(
            self::FROZEN_MS - DatabasePuller::DEFAULT_RESERVATION_TIMEOUT_MS,
            $update->bound['stale']
        );
    }

    public function testAnEligibleRowIsReadBackAsAJob(): void
    {
        $this->seed(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->getAll());
    }

    public function testClaimingMarksTheRowReserved(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $this->puller()->receive();

        $update = $this->manager->getStatements('UPDATE')[0];

        self::assertStringContainsString('SET reserved_at_ms = :now', $update->query);
        self::assertStringContainsString('reserved_at_ms IS NULL', $update->query);
        self::assertSame(self::ROW_ID, $update->bound['id']);
        self::assertSame(self::FROZEN_MS, $update->bound['now']);
    }

    public function testARowAnotherWorkerClaimedFirstIsNotHandedOut(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));
        // One row count per statement: the select reads its row, then the
        // conditional update matches nothing, so the race was lost
        $this->manager->rowCounts = [1, 0];

        self::assertNull($this->puller()->receive());
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeTakesTheRowOffTheTable(JobResult $result): void
    {
        $puller = $this->reserved();

        $puller->settle(new JobFactory()->create(self::NAME), $result, new InMemoryClient());

        $deletes = $this->manager->getStatements('DELETE');

        self::assertCount(1, $deletes);
        self::assertSame(self::ROW_ID, $deletes[0]->bound['id']);
    }

    public function testARetryTakesTheRowOffAndHandsBackAnIncrementedJob(): void
    {
        $puller = $this->reserved();
        $client = new InMemoryClient();

        $puller->settle(new Job(name: self::NAME, attempts: 2), JobResult::RETRY, $client);

        // The spent row goes; the retry arrives as a fresh one
        self::assertCount(1, $this->manager->getStatements('DELETE'));
        self::assertSame(3, $client->getPushed()[0]->getAttempts());
    }

    public function testTheRetryHoldStaysFrameworkOwned(): void
    {
        // Unlike AMQP or SQS, a database has no backoff of its own, so the
        // ramp applies here exactly as it does for Redis
        $puller = $this->reserved();
        $client = new RecordingClientFixture();

        $puller->settle(
            new Job(name: self::NAME, attempts: 2, retryDelayMs: 1000, retryDelayMultiplyByAttempt: true),
            JobResult::RETRY,
            $client
        );

        self::assertSame([2000], $client->delays);
    }

    public function testSettlingWithNothingReservedDoesNothing(): void
    {
        $this->puller()->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->manager->getStatements('DELETE'));
    }

    public function testARowIsSettledOnlyOnce(): void
    {
        $puller = $this->reserved();

        $puller->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());
        $puller->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->manager->getStatements('DELETE'));
    }

    public function testDisconnectHandsAReservedRowBack(): void
    {
        $puller = $this->reserved();

        // A worker shutting down mid-job must not leave a row no other worker
        // will ever claim
        $puller->disconnect();

        $updates = $this->manager->getStatements('UPDATE');

        self::assertCount(2, $updates);
        self::assertStringContainsString('SET reserved_at_ms = NULL', $updates[1]->query);
        self::assertSame(self::ROW_ID, $updates[1]->bound['id']);
    }

    public function testDisconnectWithNothingReservedHandsBackNothing(): void
    {
        $puller = $this->puller();
        $puller->connect();
        $puller->disconnect();

        self::assertSame([], $this->manager->getStatements('UPDATE'));
    }

    protected function seed(Job $job): void
    {
        $this->manager->rows = [
            [
                'id'       => self::ROW_ID,
                'envelope' => new JobFactory()->toJson($job),
            ],
        ];
    }

    protected function reserved(): DatabasePuller
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $puller = $this->puller();
        $puller->receive();

        return $puller;
    }

    protected function puller(): DatabasePuller
    {
        return new DatabasePuller($this->manager, self::QUEUE);
    }
}
