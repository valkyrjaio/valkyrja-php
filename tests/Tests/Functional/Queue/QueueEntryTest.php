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
use Valkyrja\Application\Entry\PullQueue;
use Valkyrja\Http\Message\Enum\StatusCode;
use Valkyrja\Http\Message\Request\ServerRequest;
use Valkyrja\Http\Message\Stream\Stream;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Client\PullerFixture;
use Valkyrja\Tests\Fixtures\Queue\Entry\PushQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
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
    }

    #[Override]
    protected function tearDown(): void
    {
        ResultLogMiddlewareFixture::reset();
        PushQueueFixture::reset();

        parent::tearDown();
    }

    public function testThePullLoopHandlesEveryDelivery(): void
    {
        $first  = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);
        $second = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $puller = new PullerFixture([$first, $second]);

        $this->loop($puller, maxJobs: 2);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($first->getId()));
        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($second->getId()));
    }

    public function testThePullEntryBootstrapsOnceThenLoops(): void
    {
        $job    = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);
        $puller = new PullerFixture([$job]);

        PullQueue::run(
            config: $this->config(),
            puller: $puller,
            client: new InMemoryClient(),
            maxJobs: 1,
        );

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertFalse($puller->connected);
    }

    public function testThePullLoopConnectsAndAlwaysDisconnects(): void
    {
        $puller = new PullerFixture([new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK)]);

        $this->loop($puller, maxJobs: 1);

        self::assertFalse($puller->connected);
    }

    public function testThePullLoopKeepsPollingThroughATimeout(): void
    {
        // A null delivery is a poll that timed out; the loop must come back
        $job    = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);
        $puller = new PullerFixture([null, null, $job]);

        $this->loop($puller, maxJobs: 1);

        self::assertSame(3, $puller->receiveCount);
        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
    }

    public function testThePullLoopRequeuesARetryThroughTheClient(): void
    {
        $job    = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);
        $client = new InMemoryClient();

        $this->loop(new PullerFixture([$job]), maxJobs: 1, client: $client);

        // Handled once; the retry went back to the processor rather than looping here
        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertCount(1, $client->getPushed());
        self::assertSame(2, $client->getPushed()[0]->getAttempts());
    }

    public function testThePullLoopArmsATimeBoundWhenOneIsGiven(): void
    {
        $job    = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);
        $puller = new PullerFixture([$job]);

        // A generous deadline is armed but not reached, so the job bound is
        // what ends the loop; the bound arithmetic itself is unit-tested
        $this->loop($puller, maxJobs: 1, maxSeconds: 60);

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

        self::assertNotNull(PushQueueFixture::$sent);
        self::assertSame(StatusCode::SERVICE_UNAVAILABLE, PushQueueFixture::$sent->getStatusCode());
    }

    public function testThePushEntrySettlesThroughAClientWhenOneIsSupplied(): void
    {
        $job    = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);
        $client = new InMemoryClient();

        PushQueueFixture::run(
            config: $this->config(),
            request: $this->request($job),
            client: $client,
        );

        self::assertCount(1, $client->getPushed());
        self::assertSame(2, $client->getPushed()[0]->getAttempts());
    }

    public function testThePushEntryLeavesRedeliveryToTheProcessorWithoutAClient(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);

        PushQueueFixture::run(
            config: $this->config(),
            request: $this->request($job),
        );

        // The status is the retry signal, so nothing is re-queued here
        self::assertNotNull(PushQueueFixture::$sent);
        self::assertSame(StatusCode::SERVICE_UNAVAILABLE, PushQueueFixture::$sent->getStatusCode());
    }

    protected function loop(
        PullerFixture $puller,
        int $maxJobs = 0,
        int $maxSeconds = 0,
        InMemoryClient|null $client = null,
    ): void {
        $app = PullQueue::bootstrap($this->config());

        PullQueue::loop(
            app: $app,
            puller: $puller,
            client: $client ?? new InMemoryClient(),
            maxJobs: $maxJobs,
            maxSeconds: $maxSeconds,
        );
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
        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
