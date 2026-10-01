<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry\Database;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Application\Entry\Database\DatabaseQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Data\QueueDatabaseClientConfig;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Application\Entry\DatabaseQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\DatabaseManagerFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\RecordingClientFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class DatabaseQueueTest extends TestCase
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

        DatabaseQueueFixture::inject($this->manager, self::QUEUE);
    }

    #[Override]
    protected function tearDown(): void
    {
        DatabaseQueueFixture::reset();

        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testConnectReadsTheQueueAndTableFromTheConfig(): void
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn(
            new QueueDatabaseClientConfig(databaseQueue: 'emails', databaseTable: 'jobs_test')
        );

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        DatabaseQueueFixture::connect($app);
        DatabaseQueueFixture::receive();

        $select = $this->manager->getStatements('SELECT')[0];

        // A table that differs from the injected default, so the assertion
        // fails if connect() stops reading the table from the config
        self::assertStringContainsString('FROM jobs_test', $select->query);
        self::assertSame('emails', $select->bound['queue']);
    }

    public function testAnEmptyTableYieldsNothing(): void
    {
        self::assertNull(DatabaseQueueFixture::receive());
    }

    public function testTheSelectSkipsHeldAndReservedRows(): void
    {
        DatabaseQueueFixture::receive();

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

        self::assertNull(DatabaseQueueFixture::receive());
        self::assertSame([], $this->manager->getStatements('UPDATE'));
    }

    public function testARowWithNoIdIsSkipped(): void
    {
        $this->manager->rows = [['envelope' => new JobFactory()->toJson(new JobFactory()->create(self::NAME))]];

        self::assertNull(DatabaseQueueFixture::receive());
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

        $job = DatabaseQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(self::ROW_ID, $this->manager->getStatements('UPDATE')[0]->bound['id']);
    }

    public function testAStaleReservationBecomesEligibleAgain(): void
    {
        // A worker that dies between the claim and the settle leaves the row
        // reserved. Without the staleness window no worker could ever take it.
        DatabaseQueueFixture::receive();

        $select = $this->manager->getStatements('SELECT')[0];

        self::assertStringContainsString('reserved_at_ms IS NULL OR reserved_at_ms <= :stale', $select->query);
        self::assertSame(
            self::FROZEN_MS - DatabaseQueue::DEFAULT_RESERVATION_TIMEOUT_MS,
            $select->bound['stale']
        );
    }

    public function testAClaimCanTakeAStaleReservation(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        DatabaseQueueFixture::receive();

        $update = $this->manager->getStatements('UPDATE')[0];

        // The claim must accept the same window the select offered, or a stale
        // row would be selected forever and never actually taken
        self::assertStringContainsString('reserved_at_ms IS NULL OR reserved_at_ms <= :stale', $update->query);
        self::assertSame(
            self::FROZEN_MS - DatabaseQueue::DEFAULT_RESERVATION_TIMEOUT_MS,
            $update->bound['stale']
        );
    }

    public function testAnEligibleRowIsReadBackAsAJob(): void
    {
        $this->seed(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $job = DatabaseQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->getAll());
    }

    public function testClaimingMarksTheRowReserved(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        DatabaseQueueFixture::receive();

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

        self::assertNull(DatabaseQueueFixture::receive());
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeTakesTheRowOffTheTable(JobResult $result): void
    {
        $this->reserved();

        DatabaseQueueFixture::settle(new JobFactory()->create(self::NAME), $result, new InMemoryClient());

        $deletes = $this->manager->getStatements('DELETE');

        self::assertCount(1, $deletes);
        self::assertSame(self::ROW_ID, $deletes[0]->bound['id']);
    }

    public function testARetryTakesTheRowOffAndHandsBackAnIncrementedJob(): void
    {
        $this->reserved();
        $client = new InMemoryClient();

        DatabaseQueueFixture::settle(new Job(name: self::NAME, attempts: 2), JobResult::RETRY, $client);

        // The spent row goes; the retry arrives as a fresh one
        self::assertCount(1, $this->manager->getStatements('DELETE'));
        self::assertSame(3, $client->getPushed()[0]->getAttempts());
    }

    public function testTheRetryHoldStaysFrameworkOwned(): void
    {
        // Unlike AMQP or SQS, a database has no backoff of its own, so the
        // ramp applies here exactly as it does for Redis
        $this->reserved();
        $client = new RecordingClientFixture();

        DatabaseQueueFixture::settle(
            new Job(name: self::NAME, attempts: 2, retryDelayMs: 1000, retryDelayMultiplyByAttempt: true),
            JobResult::RETRY,
            $client
        );

        self::assertSame([2000], $client->delays);
    }

    public function testSettlingWithNothingReservedDoesNothing(): void
    {
        DatabaseQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->manager->getStatements('DELETE'));
    }

    public function testARowIsSettledOnlyOnce(): void
    {
        $this->reserved();

        DatabaseQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());
        DatabaseQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->manager->getStatements('DELETE'));
    }

    public function testDisconnectHandsAReservedRowBack(): void
    {
        $this->reserved();

        // A worker shutting down mid-job must not leave a row no other worker
        // will ever claim
        DatabaseQueueFixture::disconnect();

        $updates = $this->manager->getStatements('UPDATE');

        self::assertCount(2, $updates);
        self::assertStringContainsString('SET reserved_at_ms = NULL', $updates[1]->query);
        self::assertSame(self::ROW_ID, $updates[1]->bound['id']);
    }

    public function testDisconnectWithNothingReservedHandsBackNothing(): void
    {
        DatabaseQueueFixture::disconnect();

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

    protected function reserved(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        DatabaseQueueFixture::receive();
    }
}
