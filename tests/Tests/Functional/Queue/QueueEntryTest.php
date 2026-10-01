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
use Valkyrja\Application\Entry\Queue;
use Valkyrja\Http\Message\Enum\StatusCode;
use Valkyrja\Http\Message\Request\ServerRequest;
use Valkyrja\Http\Message\Stream\Stream;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Application\Data\QueueWorkerConfigFixture;
use Valkyrja\Tests\Fixtures\Application\Entry\ScriptedQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Entry\PushQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function json_encode;

final class QueueEntryTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ResultLogMiddlewareFixture::reset();
        PushQueueFixture::reset();
        ScriptedQueueFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        ResultLogMiddlewareFixture::reset();
        PushQueueFixture::reset();
        ScriptedQueueFixture::reset();

        parent::tearDown();
    }

    public function testThePullLoopHandlesEveryDelivery(): void
    {
        $first  = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);
        $second = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        ScriptedQueueFixture::script([$first, $second]);

        $this->loop(maxJobs: 2);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($first->getId()));
        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($second->getId()));
    }

    public function testThePullEntryBootstrapsOnceThenLoops(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        ScriptedQueueFixture::script([$job]);

        ScriptedQueueFixture::run(config: $this->config(), maxJobs: 1);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertFalse(ScriptedQueueFixture::$connected);
    }

    public function testThePullLoopConnectsAndAlwaysDisconnects(): void
    {
        ScriptedQueueFixture::script([new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK)]);

        $this->loop(maxJobs: 1);

        self::assertFalse(ScriptedQueueFixture::$connected);
    }

    public function testThePullLoopKeepsPollingThroughATimeout(): void
    {
        // A null delivery is a poll that timed out; the loop must come back
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        ScriptedQueueFixture::script([null, null, $job]);

        $this->loop(maxJobs: 1);

        self::assertSame(3, ScriptedQueueFixture::$receiveCount);
        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testThePullLoopRequeuesARetryThroughTheClient(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);

        ScriptedQueueFixture::script([$job]);

        $client = $this->loop(maxJobs: 1);

        // Handled once; the retry went back to the processor rather than looping here
        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertCount(1, $client->getPushed());
        self::assertSame(2, $client->getPushed()[0]->getAttempts());
    }

    public function testThePullLoopArmsATimeBoundWhenOneIsGiven(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        ScriptedQueueFixture::script([$job]);

        // A generous deadline is armed but not reached, so the job bound is
        // what ends the loop; the bound arithmetic itself is unit-tested
        $this->loop(maxJobs: 1, maxSeconds: 60);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testThePushEntryAcknowledgesASuccessfulJob(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        PushQueueFixture::run(
            config: $this->config(),
            request: $this->request($job),
        );

        self::assertNotNull(PushQueueFixture::$sent);
        self::assertSame(StatusCode::NO_CONTENT, PushQueueFixture::$sent->getStatusCode());
        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testThePushEntryAsksForRedeliveryWithANonTwoHundred(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);

        PushQueueFixture::run(
            config: $this->config(),
            request: $this->request($job),
        );

        // The status is the whole signal: a push settles nothing out of band
        self::assertNotNull(PushQueueFixture::$sent);
        self::assertSame(StatusCode::SERVICE_UNAVAILABLE, PushQueueFixture::$sent->getStatusCode());
    }

    public function testTheSingleShotEntryRunsOneJob(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        Queue::run(config: $this->config(), job: $job);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testTheSingleShotEntryLeavesSettlementToAProcessorEntry(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 3);

        Queue::run(config: $this->config(), job: $job);

        // The job ran and reported a retry, but the agnostic entry has no
        // processor to hand it back to, so nothing is re-queued
        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    /**
     * Drive the scripted loop, returning the client the worker resolved.
     *
     * @param int<0, max> $maxJobs    The job bound
     * @param int<0, max> $maxSeconds The time bound
     */
    protected function loop(int $maxJobs = 0, int $maxSeconds = 0): ClientContract
    {
        $app    = ScriptedQueueFixture::bootstrap($this->config());
        $client = $app->getContainer()->getSingleton(ClientContract::class);

        ScriptedQueueFixture::loop(
            app: $app,
            maxJobs: $maxJobs,
            maxSeconds: $maxSeconds,
        );

        return $client;
    }

    protected function request(Job $job): ServerRequest
    {
        $body = new Stream();
        $body->write((string) json_encode($job->asArray()));
        $body->rewind();

        return new ServerRequest(body: $body);
    }

    protected function config(): QueueConfigContract
    {
        return new QueueWorkerConfigFixture();
    }
}
