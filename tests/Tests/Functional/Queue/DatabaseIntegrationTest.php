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
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\PullQueue;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Orm\Manager\MysqlManager;
use Valkyrja\Queue\Client\Manager\DatabaseClient;
use Valkyrja\Queue\Client\Puller\DatabasePuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
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
    }

    #[Override]
    protected function tearDown(): void
    {
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

        $received = $this->puller()->receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());
    }

    public function testAHeldJobStaysInvisibleUntilItsInstantPasses(): void
    {
        $this->client()->push(new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 60_000));

        self::assertNull($this->puller()->receive());
        self::assertSame(1, $this->rowCount());
    }

    public function testADueHeldJobIsHandedOut(): void
    {
        $this->client()->push(new Job(name: QueueRoutingProviderFixture::ALWAYS_ACK, delayMs: 1));

        usleep(5_000);

        self::assertNotNull($this->puller()->receive());
    }

    public function testAReservedRowIsNotHandedOutTwice(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertNotNull($this->puller()->receive());
        // A second worker looking at the same table must not see it
        self::assertNull($this->puller()->receive());
    }

    public function testAnAcknowledgedJobLeavesTheTable(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame(0, $this->rowCount());
    }

    public function testARetryArrivesAsAFreshRowWithAnIncrementedAttempt(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5, retryDelayMs: 0);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        // The spent row went and exactly one replacement took its place
        self::assertSame(1, $this->rowCount());

        $requeued = $this->puller()->receive();

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

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame(1, $this->rowCount());
        self::assertNull($this->puller()->receive());
    }

    public function testADeadLetteredJobLeavesTheTable(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame([JobResult::FAIL], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame(0, $this->rowCount());
    }

    public function testAnEmptyTableYieldsNothing(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testDisconnectHandsAReservedRowBack(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        $puller = $this->puller();
        $puller->connect();

        self::assertNotNull($puller->receive());

        // A worker shutting down mid-job must not leave a row no other worker
        // will ever claim
        $puller->disconnect();

        self::assertNotNull($this->puller()->receive());
    }

    private function client(): DatabaseClient
    {
        return new DatabaseClient(manager: $this->manager, queue: self::QUEUE, table: self::TABLE);
    }

    private function puller(): DatabasePuller
    {
        return new DatabasePuller(manager: $this->manager, queue: self::QUEUE, table: self::TABLE);
    }

    private function config(): QueueConfigContract
    {
        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
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
            . 'INDEX queue_jobs_claim (queue, reserved_at_ms, available_at_ms, priority)'
            . ')'
        );
    }
}
