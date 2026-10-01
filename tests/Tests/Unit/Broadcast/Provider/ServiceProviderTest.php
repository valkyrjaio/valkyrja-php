<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Broadcast\Provider;

use PHPUnit\Framework\MockObject\Exception;
use Pusher\Pusher;
use Pusher\PusherException;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Broadcast\Broadcaster\Contract\BroadcasterContract;
use Valkyrja\Broadcast\Broadcaster\CryptPusherBroadcaster;
use Valkyrja\Broadcast\Broadcaster\LogBroadcaster;
use Valkyrja\Broadcast\Broadcaster\NullBroadcaster;
use Valkyrja\Broadcast\Broadcaster\PusherBroadcaster;
use Valkyrja\Broadcast\Data\BroadcastConfig;
use Valkyrja\Broadcast\Data\BroadcastLogConfig;
use Valkyrja\Broadcast\Data\BroadcastPusherConfig;
use Valkyrja\Broadcast\Data\Contract\BroadcastConfigContract;
use Valkyrja\Broadcast\Data\Contract\BroadcastLogConfigContract;
use Valkyrja\Broadcast\Data\Contract\BroadcastPusherConfigContract;
use Valkyrja\Broadcast\Provider\BroadcastServiceProvider;
use Valkyrja\Crypt\Manager\Contract\CryptContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Broadcast\Data\BroadcastConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = BroadcastServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(BroadcastConfigContract::class, new BroadcastServiceProvider()->publishers());
        self::assertArrayHasKey(BroadcastPusherConfigContract::class, new BroadcastServiceProvider()->publishers());
        self::assertArrayHasKey(BroadcastLogConfigContract::class, new BroadcastServiceProvider()->publishers());
        self::assertArrayHasKey(BroadcasterContract::class, new BroadcastServiceProvider()->publishers());
        self::assertArrayHasKey(PusherBroadcaster::class, new BroadcastServiceProvider()->publishers());
        self::assertArrayHasKey(CryptPusherBroadcaster::class, new BroadcastServiceProvider()->publishers());
        self::assertArrayHasKey(Pusher::class, new BroadcastServiceProvider()->publishers());
        self::assertArrayHasKey(LogBroadcaster::class, new BroadcastServiceProvider()->publishers());
        self::assertArrayHasKey(NullBroadcaster::class, new BroadcastServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $callback = new BroadcastServiceProvider()->publishers()[BroadcastConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(BroadcastConfigContract::class, $config = $this->container->getSingleton(BroadcastConfigContract::class));
        self::assertSame(PusherBroadcaster::class, $config->defaultBroadcaster);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new BroadcastConfigFixture());

        $callback = new BroadcastServiceProvider()->publishers()[BroadcastConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(BroadcastConfigContract::class, $config = $this->container->getSingleton(BroadcastConfigContract::class));
        self::assertSame(NullBroadcaster::class, $config->defaultBroadcaster);
    }

    public function testPublishPusherConfig(): void
    {
        $callback = new BroadcastServiceProvider()->publishers()[BroadcastPusherConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(BroadcastPusherConfigContract::class, $config = $this->container->getSingleton(BroadcastPusherConfigContract::class));
        self::assertSame('pusher-key', $config->pusherKey);
        self::assertTrue($config->pusherUseTls);
    }

    public function testPublishPusherConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new BroadcastConfigFixture());

        $callback = new BroadcastServiceProvider()->publishers()[BroadcastPusherConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(BroadcastPusherConfigContract::class, $config = $this->container->getSingleton(BroadcastPusherConfigContract::class));
        self::assertSame('test-key', $config->pusherKey);
        self::assertFalse($config->pusherUseTls);
    }

    public function testPublishLogConfig(): void
    {
        $callback = new BroadcastServiceProvider()->publishers()[BroadcastLogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(BroadcastLogConfigContract::class, $config = $this->container->getSingleton(BroadcastLogConfigContract::class));
        self::assertSame(LoggerContract::class, $config->broadcastLogLogger);
    }

    public function testPublishLogConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new BroadcastConfigFixture());

        $callback = new BroadcastServiceProvider()->publishers()[BroadcastLogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(BroadcastLogConfigContract::class, $config = $this->container->getSingleton(BroadcastLogConfigContract::class));
        self::assertSame(LoggerContract::class, $config->broadcastLogLogger);
    }

    /**
     * @throws Exception
     */
    public function testPublishBroadcaster(): void
    {
        $this->container->setSingleton(BroadcastConfigContract::class, new BroadcastConfig());
        $this->container->setSingleton(PusherBroadcaster::class, self::createStub(PusherBroadcaster::class));

        $callback = new BroadcastServiceProvider()->publishers()[BroadcasterContract::class];
        $callback($this->container);

        self::assertInstanceOf(PusherBroadcaster::class, $this->container->getSingleton(BroadcasterContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishBroadcasterWithConfiguredDefault(): void
    {
        $this->container->setSingleton(
            BroadcastConfigContract::class,
            new BroadcastConfig(defaultBroadcaster: NullBroadcaster::class)
        );
        $this->container->setSingleton(NullBroadcaster::class, self::createStub(NullBroadcaster::class));

        $callback = new BroadcastServiceProvider()->publishers()[BroadcasterContract::class];
        $callback($this->container);

        self::assertInstanceOf(NullBroadcaster::class, $this->container->getSingleton(BroadcasterContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishPusherBroadcaster(): void
    {
        $this->container->setSingleton(Pusher::class, self::createStub(Pusher::class));

        $callback = new BroadcastServiceProvider()->publishers()[PusherBroadcaster::class];
        $callback($this->container);

        self::assertInstanceOf(PusherBroadcaster::class, $this->container->getSingleton(PusherBroadcaster::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishCryptPusherBroadcaster(): void
    {
        $this->container->setSingleton(Pusher::class, self::createStub(Pusher::class));
        $this->container->setSingleton(CryptContract::class, self::createStub(CryptContract::class));

        $callback = new BroadcastServiceProvider()->publishers()[CryptPusherBroadcaster::class];
        $callback($this->container);

        self::assertInstanceOf(CryptPusherBroadcaster::class, $this->container->getSingleton(CryptPusherBroadcaster::class));
    }

    /**
     * @throws PusherException
     */
    public function testPublishPusher(): void
    {
        $this->container->setSingleton(
            BroadcastPusherConfigContract::class,
            new BroadcastPusherConfig(pusherKey: 'test-key', pusherCluster: 'eu', pusherUseTls: false)
        );

        $callback = new BroadcastServiceProvider()->publishers()[Pusher::class];
        $callback($this->container);

        self::assertInstanceOf(Pusher::class, $this->container->getSingleton(Pusher::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishLogBroadcaster(): void
    {
        $this->container->setSingleton(BroadcastLogConfigContract::class, new BroadcastLogConfig());
        $this->container->setSingleton(LoggerContract::class, self::createStub(LoggerContract::class));

        $callback = new BroadcastServiceProvider()->publishers()[LogBroadcaster::class];
        $callback($this->container);

        self::assertInstanceOf(LogBroadcaster::class, $this->container->getSingleton(LogBroadcaster::class));
    }

    public function testPublishNullBroadcaster(): void
    {
        $callback = new BroadcastServiceProvider()->publishers()[NullBroadcaster::class];
        $callback($this->container);

        self::assertInstanceOf(NullBroadcaster::class, $this->container->getSingleton(NullBroadcaster::class));
    }
}
