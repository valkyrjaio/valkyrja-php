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
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Provider\QueueClientServiceProvider;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Client\Requeuer\Requeuer;

final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = QueueClientServiceProvider::class;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setSingleton(QueueConfigContract::class, new QueueConfig());
    }

    public function testExpectedPublishers(): void
    {
        $publishers = new QueueClientServiceProvider()->publishers();

        self::assertArrayHasKey(ClientContract::class, $publishers);
        self::assertArrayHasKey(RequeuerContract::class, $publishers);
    }

    public function testTheSyncClientIsTheZeroConfigDefault(): void
    {
        $this->publish(ClientContract::class);

        self::assertInstanceOf(SyncClient::class, $this->container->getSingleton(ClientContract::class));
    }

    public function testPublishRequeuer(): void
    {
        $this->publish(RequeuerContract::class);

        self::assertInstanceOf(Requeuer::class, $this->container->getSingleton(RequeuerContract::class));
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
