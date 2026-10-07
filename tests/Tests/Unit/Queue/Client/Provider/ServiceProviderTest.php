<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Provider;

use Override;
use PHPUnit\Framework\MockObject\Exception;
use Predis\ClientInterface;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Queue\Client\Data\Contract\QueueAmqpClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueDeferredClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueRedisClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSyncClientConfigContract;
use Valkyrja\Queue\Client\Data\QueueAmqpClientConfig;
use Valkyrja\Queue\Client\Data\QueueClientConfig;
use Valkyrja\Queue\Client\Data\QueueRedisClientConfig;
use Valkyrja\Queue\Client\Manager\AmqpClient;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DeferredClient;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Provider\QueueClientServiceProvider;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientConfigNotFoundException;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Tests\Fixtures\Application\Entry\InternalQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\Data\QueueClientConfigFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;

final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = QueueClientServiceProvider::class;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ResultLogMiddlewareFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        ResultLogMiddlewareFixture::reset();

        parent::tearDown();
    }

    public function testExpectedPublishers(): void
    {
        $publishers = new QueueClientServiceProvider()->publishers();

        self::assertArrayHasKey(QueueClientConfigContract::class, $publishers);
        self::assertArrayHasKey(QueueSyncClientConfigContract::class, $publishers);
        self::assertArrayHasKey(QueueDeferredClientConfigContract::class, $publishers);
        self::assertArrayHasKey(QueueRedisClientConfigContract::class, $publishers);
        self::assertArrayHasKey(QueueAmqpClientConfigContract::class, $publishers);
        self::assertArrayHasKey(ClientContract::class, $publishers);
        self::assertArrayHasKey(SyncClient::class, $publishers);
        self::assertArrayHasKey(DeferredClient::class, $publishers);
        self::assertArrayHasKey(InMemoryClient::class, $publishers);
        self::assertArrayHasKey(RedisClient::class, $publishers);
        self::assertArrayHasKey(AmqpClient::class, $publishers);
    }

    public function testPublishConfig(): void
    {
        $this->publish(QueueClientConfigContract::class);

        self::assertSame(
            RedisClient::class,
            $this->container->getSingleton(QueueClientConfigContract::class)->defaultQueueClient
        );
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new QueueClientConfigFixture());

        $this->publish(QueueClientConfigContract::class);

        self::assertSame(
            SyncClient::class,
            $this->container->getSingleton(QueueClientConfigContract::class)->defaultQueueClient
        );
    }

    public function testPublishSyncConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new QueueClientConfigFixture());

        $this->publish(QueueSyncClientConfigContract::class);

        self::assertSame(
            InternalQueueFixture::class,
            $this->container->getSingleton(QueueSyncClientConfigContract::class)->syncEntry
        );
    }

    public function testPublishSyncConfigWithoutApplicationConfigThrows(): void
    {
        $this->expectException(QueueClientConfigNotFoundException::class);
        $this->expectExceptionMessage(QueueSyncClientConfigContract::class);

        $this->publish(QueueSyncClientConfigContract::class);
    }

    public function testPublishDeferredConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new QueueClientConfigFixture());

        $this->publish(QueueDeferredClientConfigContract::class);

        self::assertSame(
            InternalQueueFixture::class,
            $this->container->getSingleton(QueueDeferredClientConfigContract::class)->deferredEntry
        );
    }

    public function testPublishDeferredConfigWithoutApplicationConfigThrows(): void
    {
        $this->expectException(QueueClientConfigNotFoundException::class);
        $this->expectExceptionMessage(QueueDeferredClientConfigContract::class);

        $this->publish(QueueDeferredClientConfigContract::class);
    }

    public function testPublishRedisConfig(): void
    {
        $this->publish(QueueRedisClientConfigContract::class);

        $config = $this->container->getSingleton(QueueRedisClientConfigContract::class);

        self::assertSame('127.0.0.1', $config->redisHost);
        self::assertSame(6379, $config->redisPort);
        self::assertSame('queues:default', $config->redisQueue);
    }

    public function testPublishRedisConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new QueueClientConfigFixture());

        $this->publish(QueueRedisClientConfigContract::class);

        $config = $this->container->getSingleton(QueueRedisClientConfigContract::class);

        self::assertSame('redis.test', $config->redisHost);
        self::assertSame(6380, $config->redisPort);
        self::assertSame('queues:test', $config->redisQueue);
    }

    public function testPublishAmqpConfig(): void
    {
        $this->publish(QueueAmqpClientConfigContract::class);

        $config = $this->container->getSingleton(QueueAmqpClientConfigContract::class);

        self::assertSame('127.0.0.1', $config->amqpHost);
        self::assertSame(5672, $config->amqpPort);
        self::assertSame('queues.default', $config->amqpQueue);
    }

    public function testPublishAmqpConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new QueueClientConfigFixture());

        $this->publish(QueueAmqpClientConfigContract::class);

        $config = $this->container->getSingleton(QueueAmqpClientConfigContract::class);

        self::assertSame('amqp.test', $config->amqpHost);
        self::assertSame(5673, $config->amqpPort);
        self::assertSame('queues.test', $config->amqpQueue);
    }

    /**
     * @throws Exception
     */
    public function testPublishClient(): void
    {
        $redis = new RedisClient(redis: self::createStub(ClientInterface::class));

        $this->container->setSingleton(QueueClientConfigContract::class, new QueueClientConfig());
        $this->container->setSingleton(RedisClient::class, $redis);

        $this->publish(ClientContract::class);

        self::assertSame($redis, $this->container->getSingleton(ClientContract::class));
    }

    public function testPublishClientWithConfiguredDefault(): void
    {
        $inMemory = new InMemoryClient();

        $this->container->setSingleton(
            QueueClientConfigContract::class,
            new QueueClientConfig(defaultQueueClient: InMemoryClient::class)
        );
        $this->container->setSingleton(InMemoryClient::class, $inMemory);

        $this->publish(ClientContract::class);

        self::assertSame($inMemory, $this->container->getSingleton(ClientContract::class));
    }

    public function testPublishSyncClient(): void
    {
        $this->container->setSingleton(ConfigContract::class, new QueueClientConfigFixture());
        $this->container->setSingleton(QueueSyncClientConfigContract::class, new QueueClientConfigFixture());

        $this->publish(SyncClient::class);

        $client = $this->container->getSingleton(SyncClient::class);
        $client->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertSame('host php/' . ApplicationInfo::VERSION, $client->getPushed()[0]->getProducer());
        self::assertCount(1, ResultLogMiddlewareFixture::getLog());
    }

    public function testPublishDeferredClient(): void
    {
        $this->container->setSingleton(ConfigContract::class, new QueueClientConfigFixture());
        $this->container->setSingleton(QueueDeferredClientConfigContract::class, new QueueClientConfigFixture());

        $this->publish(DeferredClient::class);

        $client = $this->container->getSingleton(DeferredClient::class);
        $client->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));
        $client->drain();

        self::assertSame('host php/' . ApplicationInfo::VERSION, $client->getPushed()[0]->getProducer());
        self::assertCount(1, ResultLogMiddlewareFixture::getLog());
    }

    public function testPublishInMemoryClient(): void
    {
        $this->container->setSingleton(ConfigContract::class, new QueueClientConfigFixture());

        $this->publish(InMemoryClient::class);

        $client = $this->container->getSingleton(InMemoryClient::class);
        $client->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertSame('host php/' . ApplicationInfo::VERSION, $client->getPushed()[0]->getProducer());
    }

    public function testPublishRedisClient(): void
    {
        $this->container->setSingleton(QueueRedisClientConfigContract::class, new QueueRedisClientConfig());

        $this->publish(RedisClient::class);

        self::assertInstanceOf(RedisClient::class, $this->container->getSingleton(RedisClient::class));
    }

    public function testPublishAmqpClientDoesNotConnect(): void
    {
        $this->container->setSingleton(QueueAmqpClientConfigContract::class, new QueueAmqpClientConfig());

        // Nothing listens on the default port, so an eager connection would throw here
        $this->publish(AmqpClient::class);

        self::assertInstanceOf(AmqpClient::class, $this->container->getSingleton(AmqpClient::class));
    }

    /**
     * @param class-string $contract
     */
    protected function publish(string $contract): void
    {
        $callback = new QueueClientServiceProvider()->publishers()[$contract];

        $callback($this->container);
    }
}
