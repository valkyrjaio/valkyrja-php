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
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Manager\AmqpClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Application\Data\AmqpWorkerConfigFixture;
use Valkyrja\Tests\Fixtures\Application\Entry\AmqpQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_int;
use function is_string;
use function parse_url;

final class AmqpIntegrationTest extends TestCase
{
    /** @var non-empty-string */
    private const string QUEUE = 'valkyrja.tests.queue';

    private AMQPStreamConnection $connection;

    private AMQPChannel $channel;

    /** @var non-empty-string */
    private string $amqpHost = '127.0.0.1';

    private int $amqpPort = 5672;

    /** @var non-empty-string */
    private string $amqpUser = 'guest';

    private string $amqpPassword = 'guest';

    /** @var non-empty-string */
    private string $amqpVhost = '/';

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

        $this->amqpHost     = is_string($parts['host'] ?? null) && $parts['host'] !== '' ? $parts['host'] : '127.0.0.1';
        $this->amqpPort     = is_int($parts['port'] ?? null) ? $parts['port'] : 5672;
        $this->amqpUser     = is_string($parts['user'] ?? null) && $parts['user'] !== '' ? $parts['user'] : 'guest';
        $this->amqpPassword = is_string($parts['pass'] ?? null) ? $parts['pass'] : 'guest';

        $this->connection = new AMQPStreamConnection(
            $this->amqpHost,
            $this->amqpPort,
            $this->amqpUser,
            $this->amqpPassword,
        );

        $this->channel = $this->connection->channel();

        $this->purge();

        AmqpQueueFixture::inject($this->channel, self::QUEUE, timeout: 0);
        // A worker declares the queue and limits its prefetch as it connects
        AmqpQueueFixture::connect($this->application());

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

        AmqpQueueFixture::reset();

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

        // Re-declaring is a synchronous round-trip, so the publish has landed
        // by the time the first get asks for it
        AmqpQueueFixture::connect($this->application());

        $received = AmqpQueueFixture::receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());

        AmqpQueueFixture::settle($received, JobResult::ACK, $client);
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
        self::assertNull(AmqpQueueFixture::receive());
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $client = $this->client();
        $client->declareQueue();
        $client->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        AmqpQueueFixture::connect($this->application());

        self::assertNotNull(AmqpQueueFixture::receive());

        // A worker shutting down mid-job must not make the broker wait out its
        // own timeout before another worker can take it
        AmqpQueueFixture::disconnect();

        $this->channel = $this->connection->channel();

        self::assertSame(1, $this->depth());
    }

    /**
     * Run one job through the worker, with the entry settling its own outcome.
     */
    private function consumeOne(AmqpClient $client): void
    {
        AmqpQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );
    }

    private function client(): AmqpClient
    {
        return new AmqpClient(connection: $this->connection, queue: self::QUEUE);
    }

    /**
     * Build an application whose container carries the AMQP client config.
     */
    private function application(): ApplicationContract
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn($this->config());

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        return $app;
    }

    private function config(): QueueConfigContract
    {
        return new AmqpWorkerConfigFixture(
            amqpHost: $this->amqpHost,
            amqpPort: $this->amqpPort,
            amqpUser: $this->amqpUser,
            amqpPassword: $this->amqpPassword,
            amqpVhost: $this->amqpVhost,
            amqpQueue: self::QUEUE,
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
