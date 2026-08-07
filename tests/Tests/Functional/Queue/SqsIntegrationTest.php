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

use AsyncAws\Sqs\SqsClient as Sqs;
use Override;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\PullQueue;
use Valkyrja\Queue\Client\Manager\SqsClient;
use Valkyrja\Queue\Client\Puller\SqsPuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_string;

final class SqsIntegrationTest extends TestCase
{
    private Sqs $sqs;

    /** @var non-empty-string */
    private string $queueUrl;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = getenv('SQS_ENDPOINT');
        $queueUrl = getenv('SQS_QUEUE_URL');

        if (! is_string($endpoint) || $endpoint === '' || ! is_string($queueUrl) || $queueUrl === '') {
            self::markTestSkipped('Set SQS_ENDPOINT and SQS_QUEUE_URL to a reachable SQS endpoint to run this test.');
        }

        if (! class_exists(Sqs::class)) {
            self::markTestSkipped('The async-aws/sqs package is not installed.');
        }

        $this->queueUrl = $queueUrl;
        $this->sqs      = new Sqs([
            'endpoint'          => $endpoint,
            'region'            => 'us-east-1',
            'accessKeyId'       => 'test',
            'accessKeySecret'   => 'test',
            'pathStyleEndpoint' => true,
        ]);

        $this->purge();

        ResultLogMiddlewareFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->sqs)) {
            $this->purge();
        }

        ResultLogMiddlewareFixture::reset();

        parent::tearDown();
    }

    public function testAPublishedJobRoundTripsThroughTheQueueUnchanged(): void
    {
        $job = new Job(
            name: QueueRoutingProviderFixture::ALWAYS_ACK,
            payload: new JobFactory()->create('x', ['user_id' => 42, 'nested' => ['a' => 1]])->getPayload(),
            id: 'stable-id',
            maxAttempts: 7,
            priority: 3,
        );

        $client = $this->client();
        $client->push($job);

        $puller   = $this->puller();
        $received = $puller->receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());

        $puller->settle($received, JobResult::ACK, $client);
    }

    public function testAnAcknowledgedJobIsGoneForGood(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertNull($this->puller()->receive());
    }

    public function testARetriedJobIsRedeliveredByTheQueue(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);

        $client = $this->client();
        $client->push($job);

        PullQueue::run(
            config: $this->config(),
            puller: $puller = $this->puller(),
            client: $client,
            maxJobs: 1,
            requeuer: $puller,
        );

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        // A processor-owned retry is not a re-publish, so the client is untouched
        self::assertCount(1, $client->getPushed());
        // The queue made it visible again rather than dropping it
        self::assertNotNull($this->puller()->receive());
    }

    public function testAnEmptyQueueYieldsNothing(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        $puller = $this->puller();
        $puller->connect();

        self::assertNotNull($puller->receive());

        // A worker shutting down mid-job must not make the queue wait out the
        // whole visibility timeout before another worker can take it
        $puller->disconnect();

        self::assertNotNull($this->puller()->receive());
    }

    private function client(): SqsClient
    {
        return new SqsClient(sqs: $this->sqs, queueUrl: $this->queueUrl);
    }

    private function puller(): SqsPuller
    {
        return new SqsPuller(
            sqs: $this->sqs,
            queueUrl: $this->queueUrl,
            waitTimeSeconds: 0,
            visibilityTimeout: 30,
        );
    }

    private function config(): QueueConfigContract
    {
        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }

    private function purge(): void
    {
        $this->sqs->purgeQueue(['QueueUrl' => $this->queueUrl]);
    }
}
