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
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientSyncJobFailedException;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Tests\Fixtures\Application\Entry\InternalQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class SyncClientTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ResultLogMiddlewareFixture::reset();
        InternalQueueFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        ResultLogMiddlewareFixture::reset();
        InternalQueueFixture::reset();

        parent::tearDown();
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

    public function testTheQueueApplicationBootsOnceForEveryJob(): void
    {
        $client = $this->client();
        $first  = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);
        $second = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client->push($first);
        $client->push($second);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($first->getId()));
        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($second->getId()));
        self::assertSame(1, InternalQueueFixture::$configCount);
    }

    protected function client(): SyncClient
    {
        return new SyncClient(entry: InternalQueueFixture::class);
    }
}
