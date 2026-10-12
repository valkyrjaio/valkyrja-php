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
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Application\Entry\InternalQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Handler\JobOutcomeFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class SyncClientTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ResultLogMiddlewareFixture::reset();
        SettlingResultLogMiddlewareFixture::reset();
        InternalQueueFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        JobOutcomeFixture::reset();

        ResultLogMiddlewareFixture::reset();
        SettlingResultLogMiddlewareFixture::reset();
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

    public function testAnExhaustedWorkerShutdownEndsAsATerminalOutcome(): void
    {
        // The retry policy answers a shutdown without spending an attempt,
        // because a broker would hand the job to another worker. There is no
        // other worker in process, so the entry ends the chain itself. Without
        // that cap this test never returns, and without the terminal outcome
        // the caller of a sync push is told the job succeeded.
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_SHUTS_DOWN, maxAttempts: 3);

        $client = $this->client();

        try {
            $client->push($job);

            self::fail('A sync push must report a job it abandoned.');
        } catch (QueueClientSyncJobFailedException) {
            // The throw is the report, and the log below is the record
        }

        self::assertSame(
            [JobResult::RETRY, JobResult::RETRY, JobResult::DEAD_LETTER],
            ResultLogMiddlewareFixture::getResults($job->getId())
        );

        // The settling stage has the last word on the outcome, so it has to see
        // the terminal one too. Capping after `settlingResult()` would leave a
        // RETRY here while the assertion above still passed.
        self::assertSame(
            [JobResult::RETRY, JobResult::RETRY, JobResult::DEAD_LETTER],
            SettlingResultLogMiddlewareFixture::getResults($job->getId())
        );
    }

    public function testOnlyTheFirstTerminalFailureSurfaces(): void
    {
        $client = $this->client();
        $outer  = new JobFactory()->create(QueueRoutingProviderFixture::PUSHES_THEN_FAILS);

        // The handler pushes a second job through the same client, and that one
        // also gives up, so the drain settles two terminal failures in a row
        JobOutcomeFixture::$client = $client;
        JobOutcomeFixture::$pushes = QueueRoutingProviderFixture::ALWAYS_FAIL;

        try {
            $client->push($outer);

            self::fail('The drain did not surface a failure.');
        } catch (QueueClientSyncJobFailedException $exception) {
            // The first failure is the one that surfaces, not the later one
            self::assertStringContainsString($outer->getId(), $exception->getMessage());
        }
    }

    protected function client(): SyncClient
    {
        return new SyncClient(entry: InternalQueueFixture::class);
    }
}
