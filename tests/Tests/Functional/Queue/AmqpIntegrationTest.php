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
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\PullQueue;
use Valkyrja\Queue\Client\Manager\AmqpClient;
use Valkyrja\Queue\Client\Puller\AmqpPuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_string;
use function parse_url;

final class AmqpIntegrationTest extends TestCase
{
    /** @var non-empty-string */
    private const string QUEUE = 'valkyrja.tests.queue';

    private AMQPStreamConnection $connection;

    private AMQPChannel $channel;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('AMQP_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Set AMQP_DSN to a reachable AMQP broker to run this test.');
        }

        if (! class_exists(AMQPStreamConnection::class)) {
            self::markTestSkipped('The php-amqplib/php-amqplib package is not installed.');
        }

        $parts = parse_url($dsn);

        $this->connection = new AMQPStreamConnection(
            $parts['host'] ?? '127.0.0.1',
            $parts['port'] ?? 5672,
            $parts['user'] ?? 'guest',
            $parts['pass'] ?? 'guest',
        );

        $this->channel = $this->connection->channel();

        $this->purge();

        ResultLogMiddlewareFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->channel) && $this->channel->is_open()) {
            $this->purge();
            $this->channel->close();
        }

        if (isset($this->connection) && $this->connection->isConnected()) {
            $this->connection->close();
        }

        ResultLogMiddlewareFixture::reset();

        parent::tearDown();
    }

    public function testAPublishedJobRoundTripsThroughTheBrokerUnchanged(): void
    {
        $job = new Job(
            name: QueueRoutingProviderFixture::ALWAYS_ACK,
            payload: new JobFactory()->create('x', ['user_id' => 42, 'nested' => ['a' => 1]])->getPayload(),
            id: 'stable-id',
            maxAttempts: 7,
            priority: 3,
        );

        $client = $this->client();
        $client->declareQueue();
        $client->push($job);

        $puller = $this->puller();
        $puller->connect();

        $received = $puller->receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());

        $puller->settle($received, JobResult::ACK, $client);
    }

    public function testAnAcknowledgedJobIsGoneForGood(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->declareQueue();
        $client->push($job);

        $this->consumeOne($client);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame(0, $this->depth());
    }

    public function testARetriedJobIsRedeliveredByTheBroker(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);

        $client = $this->client();
        $client->declareQueue();
        $client->push($job);

        $this->consumeOne($client);

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        // The broker holds it again — nothing was published, it was nacked back
        self::assertSame(1, $this->depth());
        // A processor-owned retry is not a re-publish, so the client is untouched
        self::assertCount(1, $client->getPushed());
    }

    public function testADeadLetteredJobIsNotHandedBack(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        $client = $this->client();
        $client->declareQueue();
        $client->push($job);

        $this->consumeOne($client);

        self::assertSame([JobResult::FAIL], ResultLogMiddlewareFixture::getResults($job->getId()));
        // Dropped rather than requeued: with no dead-letter exchange bound, the
        // broker discards it, which is the documented per-policy behavior
        self::assertSame(0, $this->depth());
    }

    public function testAnEmptyQueueYieldsNothing(): void
    {
        $puller = $this->puller();
        $puller->connect();

        self::assertNull($puller->receive());
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $client = $this->client();
        $client->declareQueue();
        $client->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        $puller = $this->puller();
        $puller->connect();

        self::assertNotNull($puller->receive());

        // A worker shutting down mid-job must not make the broker wait out its
        // own timeout before another worker can take it
        $puller->disconnect();

        $this->channel = $this->connection->channel();

        self::assertSame(1, $this->depth());
    }

    /**
     * Run one job through the worker, with the puller settling its own outcome.
     */
    private function consumeOne(AmqpClient $client): void
    {
        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );
    }

    private function client(): AmqpClient
    {
        return new AmqpClient(channel: $this->channel, queue: self::QUEUE);
    }

    private function puller(): AmqpPuller
    {
        return new AmqpPuller(channel: $this->channel, queue: self::QUEUE, timeout: 0);
    }

    private function config(): QueueConfigContract
    {
        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }

    /**
     * Get the number of ready messages on the queue.
     */
    private function depth(): int
    {
        $channel = $this->connection->isConnected() && $this->channel->is_open()
            ? $this->channel
            : $this->connection->channel();

        $declared = $channel->queue_declare(self::QUEUE, false, true, false, false);

        return (int) ($declared[1] ?? 0);
    }

    private function purge(): void
    {
        $this->channel->queue_declare(self::QUEUE, false, true, false, false);
        $this->channel->queue_purge(self::QUEUE);
    }
}
