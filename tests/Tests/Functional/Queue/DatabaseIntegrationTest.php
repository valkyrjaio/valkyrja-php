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
use PDO;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Orm\Manager\MysqlManager;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DatabaseClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Application\Data\DatabaseWorkerConfigFixture;
use Valkyrja\Tests\Fixtures\Application\Entry\DatabaseQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function getenv;
use function is_string;
use function usleep;

final class DatabaseIntegrationTest extends TestCase
{
    /** @var non-empty-string */
    private const string TABLE = 'valkyrja_test_queue_jobs';

    /** @var non-empty-string */
    private const string QUEUE = 'tests';

    private MysqlManager $manager;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('DATABASE_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Set DATABASE_DSN to a reachable database to run this test.');
        }

        $user     = getenv('DATABASE_USER');
        $password = getenv('DATABASE_PASSWORD');

        $pdo = new PDO(
            $dsn,
            is_string($user) ? $user : 'root',
            is_string($password) ? $password : '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->manager = new MysqlManager($pdo, new Container());

        $this->createTable();

        ResultLogMiddlewareFixture::reset();

        DatabaseQueueFixture::inject($this->manager, self::QUEUE, self::TABLE);
    }

    #[Override]
    protected function tearDown(): void
    {
        DatabaseQueueFixture::reset();

        if (isset($this->manager)) {
            $this->manager->query('DROP TABLE IF EXISTS ' . self::TABLE);
        }

        ResultLogMiddlewareFixture::reset();

        parent::tearDown();
    }

    public function testAPublishedJobRoundTripsThroughTheTableUnchanged(): void
    {
        $job = new Job(
            name: QueueRoutingProviderFixture::ALWAYS_ACK,
            payload: new JobFactory()->create('x', ['user_id' => 42, 'nested' => ['a' => 1]])->getPayload(),
            id: 'stable-id',
            maxAttempts: 7,
            priority: 3,
        );

        $client = $this->client();
        $client->push($job);

        $received = $this->poll();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());
    }

    public function testAHeldJobStaysInvisibleUntilItsInstantPasses(): void
    {
        $this->client()->push(new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 60_000));

        self::assertNull($this->poll());
        self::assertSame(1, $this->rowCount());
    }

    public function testADueHeldJobIsHandedOut(): void
    {
        $this->client()->push(new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 1));

        usleep(5_000);

        self::assertNotNull($this->poll());
    }

    public function testAReservedRowIsNotHandedOutTwice(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertNotNull($this->poll());
        // A second worker looking at the same table must not see it
        self::assertNull($this->poll());
    }

    public function testAnAcknowledgedJobLeavesTheTable(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        $this->work($client);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame(0, $this->rowCount());
    }

    public function testARetryArrivesAsAFreshRowWithAnIncrementedAttempt(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5, retryDelayMs: 0);

        $client = $this->client();
        $client->push($job);

        $this->work($client);

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        // The spent row went and exactly one replacement took its place
        self::assertSame(1, $this->rowCount());

        $requeued = $this->poll();

        self::assertNotNull($requeued);
        self::assertSame(2, $requeued->getAttempts());
        self::assertSame($job->getId(), $requeued->getId());
    }

    public function testTheFrameworkRampHoldsARetryOffTheQueue(): void
    {
        // A database has no backoff of its own, so the ramp is what times the
        // retry — the replacement row must not be eligible at once
        $job = new Job(
            name: QueueRoutingProviderFixture::ALWAYS_RETRY,
            maxAttempts: 5,
            retryDelayMs: 60_000,
        );

        $client = $this->client();
        $client->push($job);

        $this->work($client);

        self::assertSame(1, $this->rowCount());
        self::assertNull($this->poll());
    }

    public function testADeadLetteredJobLeavesTheTable(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        $client = $this->client();
        $client->push($job);

        $this->work($client);

        self::assertSame([JobResult::FAIL], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame(0, $this->rowCount());
    }

    public function testAnEmptyTableYieldsNothing(): void
    {
        self::assertNull($this->poll());
    }

    public function testDisconnectHandsAReservedRowBack(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertNotNull(DatabaseQueueFixture::receive());

        // A worker shutting down mid-job must not leave a row no other worker
        // will ever claim
        DatabaseQueueFixture::disconnect();

        self::assertNotNull($this->poll());
    }

    /**
     * Run one job through a worker whose client writes to the test table.
     */
    private function work(DatabaseClient $client): void
    {
        $app = DatabaseQueueFixture::bootstrap($this->config());

        $app->getContainer()->setSingleton(ClientContract::class, $client);

        DatabaseQueueFixture::loop($app, maxJobs: 1);
    }

    /**
     * Poll on a re-established manager, because a finished worker disconnects.
     */
    private function poll(): JobContract|null
    {
        DatabaseQueueFixture::inject($this->manager, self::QUEUE, self::TABLE);

        return DatabaseQueueFixture::receive();
    }

    private function client(): DatabaseClient
    {
        return new DatabaseClient(manager: $this->manager, queue: self::QUEUE, table: self::TABLE);
    }

    private function config(): QueueConfigContract
    {
        return new DatabaseWorkerConfigFixture(
            databaseQueue: self::QUEUE,
            databaseTable: self::TABLE,
        );
    }

    private function rowCount(): int
    {
        $statement = $this->manager->query('SELECT COUNT(*) AS total FROM ' . self::TABLE);

        return (int) $statement->fetch()['total'];
    }

    private function createTable(): void
    {
        $this->manager->query('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->manager->query(
            'CREATE TABLE ' . self::TABLE . ' ('
            . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
            . 'queue VARCHAR(255) NOT NULL,'
            . 'envelope LONGTEXT NOT NULL,'
            . 'priority INT NOT NULL DEFAULT 0,'
            . 'available_at_ms BIGINT NOT NULL,'
            . 'reserved_at_ms BIGINT NULL,'
            . 'INDEX queue_jobs_claim (queue, available_at_ms, priority)'
            . ')'
        );
    }
}
