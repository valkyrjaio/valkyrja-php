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
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Entry\Http;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\DeferredClient;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Tests\Fixtures\Queue\Client\Data\DeferredHostConfigFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\Data\SyncHostConfigFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

final class InternalClientTest extends TestCase
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

    public function testASyncPushFromAHostApplicationRunsInItsOwnQueueApplication(): void
    {
        $client = Http::app(new SyncHostConfigFixture())->getContainer()->getSingleton(ClientContract::class);
        $job    = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        // The host loads only the client provider, so the job can only run in
        // the queue application of the entry
        $client->push($job);

        self::assertInstanceOf(SyncClient::class, $client);
        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame('host php/' . ApplicationInfo::VERSION, $client->getPushed()[0]->getProducer());
    }

    public function testADeferredPushFromAHostApplicationRunsWhenDrained(): void
    {
        $client = Http::app(new DeferredHostConfigFixture())->getContainer()->getSingleton(ClientContract::class);
        $job    = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        self::assertInstanceOf(DeferredClient::class, $client);

        $client->push($job);

        self::assertSame([], ResultLogMiddlewareFixture::getResults($job->getId()));

        $client->drain();

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }
}
