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
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientSyncJobFailedException;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

final class QueueLifecycleTest extends TestCase
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

    public function testASuccessfulJobReadsBackAsASingleAck(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $this->client()->push($job);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testADeliberateFailureReadsBackAsASingleFail(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        $this->pushExpectingFailure($this->client(), $job);

        self::assertSame([JobResult::FAIL], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testARetryChainRunsToItsCeilingAndDeadLetters(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 3);

        $this->pushExpectingFailure($this->client(), $job);

        // Two retries, then the ceiling converts the third into a dead-letter
        self::assertSame(
            [JobResult::RETRY, JobResult::RETRY, JobResult::DEAD_LETTER],
            ResultLogMiddlewareFixture::getResults($job->getId())
        );
    }

    public function testTheIdIsStableAcrossTheWholeRetryChain(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 3);

        $client = $this->client();
        $this->pushExpectingFailure($client, $job);

        // One log key, so every redelivery carried the same id
        self::assertCount(1, ResultLogMiddlewareFixture::getLog());
        self::assertArrayHasKey($job->getId(), ResultLogMiddlewareFixture::getLog());
    }

    public function testEachRedeliveryIncrementsTheAttemptCount(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 3);

        $client = $this->client();
        $this->pushExpectingFailure($client, $job);

        $attempts = [];

        foreach ($client->getPushed() as $pushed) {
            $attempts[] = $pushed->getAttempts();
        }

        self::assertSame([1, 2, 3], $attempts);
    }

    public function testAThrowingJobRetriesThenDeadLetters(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_THROWS, maxAttempts: 2);

        $this->pushExpectingFailure($this->client(), $job);

        self::assertSame(
            [JobResult::RETRY, JobResult::DEAD_LETTER],
            ResultLogMiddlewareFixture::getResults($job->getId())
        );
    }

    public function testATerminalFailureSurfacesAtTheCallSite(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_FAIL);

        // A sync push blocks until the job finishes, so the caller is still
        // there to be told; an async push throws only on an enqueue error
        $this->expectException(QueueClientSyncJobFailedException::class);
        $this->expectExceptionMessage('ended in FAIL after 1 attempt(s)');

        $this->client()->push($job);
    }

    public function testAnAcknowledgedJobDoesNotThrow(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $this->client()->push($job);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testPushStampsTheFrameworkOwnedFields(): void
    {
        $client = $this->client();
        $client->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        $pushed = $client->getPushed()[0];

        self::assertSame('worker php/26.0.0', $pushed->getProducer());
        self::assertSame(1, $pushed->getAttempts());
        self::assertGreaterThan(0, $pushed->getEnqueuedAtMs());
        self::assertSame($pushed->getEnqueuedAtMs(), $pushed->getModifiedAtMs());
    }

    /**
     * Push a job whose chain ends in a terminal failure.
     *
     * The sync client surfaces that failure at the call site, so every test
     * that drives a failing job must take the throw before it reads the log.
     */
    protected function pushExpectingFailure(SyncClient $client, JobContract $job): void
    {
        try {
            $client->push($job);
        } catch (QueueClientSyncJobFailedException) {
            return;
        }

        self::fail('The sync client did not surface the terminal failure.');
    }

    protected function client(): SyncClient
    {
        return new SyncClient(config: $this->config(), version: '26.0.0');
    }

    protected function config(): QueueConfigContract
    {
        return new QueueConfig(
            dir: Directory::$basePath,
            applicationName: 'worker',
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
