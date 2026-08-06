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
use Valkyrja\Queue\Client\Manager\DeferredClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the DeferredClient.
 */
final class DeferredClientTest extends TestCase
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

    public function testPushOnlyBuffers(): void
    {
        $client = $this->client();
        $job    = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client->push($job);

        self::assertCount(1, $client->getBuffered());
        // Nothing has run yet — that is the whole point of deferring
        self::assertSame([], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testDrainRunsTheBufferedJobs(): void
    {
        $client = $this->client();
        $job    = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client->push($job);
        $client->drain();

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertSame([], $client->getBuffered());
    }

    public function testDrainWithAnEmptyBufferDoesNothing(): void
    {
        $client = $this->client();
        $client->drain();

        self::assertSame([], ResultLogMiddlewareFixture::getLog());
    }

    public function testDrainKeepsGoingUntilTheRetryChainEnds(): void
    {
        $client = $this->client();
        $job    = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 3);

        $client->push($job);
        $client->drain();

        // Once the response is out there is no later moment to finish in
        self::assertSame(
            [JobResult::RETRY, JobResult::RETRY, JobResult::DEAD_LETTER],
            ResultLogMiddlewareFixture::getResults($job->getId())
        );
        self::assertSame([], $client->getBuffered());
    }

    public function testDefaultsToItsOwnConfig(): void
    {
        self::assertSame([], new DeferredClient()->getBuffered());
    }

    protected function client(): DeferredClient
    {
        return new DeferredClient(
            config: new QueueConfig(
                dir: Directory::$basePath,
                providers: [new QueueTestComponentProviderFixture()],
                resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
            )
        );
    }
}
