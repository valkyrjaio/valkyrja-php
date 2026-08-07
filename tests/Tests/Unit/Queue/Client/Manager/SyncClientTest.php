<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Manager;

use Override;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientSyncJobFailedException;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class SyncClientTest extends TestCase
{
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

    public function testDefaultsToItsOwnConfig(): void
    {
        self::assertSame([], new SyncClient()->getPushed());
    }

    public function testRunsTheJobInline(): void
    {
        $client = $this->client();
        $job    = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client->push($job);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testATerminalFailureThrows(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        $this->expectException(QueueClientSyncJobFailedException::class);

        $this->client()->push($job);
    }

    protected function client(): SyncClient
    {
        return new SyncClient(
            config: new QueueConfig(
                dir: Directory::$basePath,
                providers: [new QueueTestComponentProviderFixture()],
                resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
            )
        );
    }
}
