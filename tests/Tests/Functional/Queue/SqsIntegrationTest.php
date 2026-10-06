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
use Valkyrja\Queue\Client\Manager\SqsClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Application\Data\SqsWorkerConfigFixture;
use Valkyrja\Tests\Fixtures\Application\Entry\SqsQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
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

    /** @var non-empty-string */
    private string $endpoint;

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
        $this->endpoint = $endpoint;
        $this->sqs      = new Sqs([
            'endpoint'          => $endpoint,
            'region'            => 'us-east-1',
            'accessKeyId'       => 'test',
            'accessKeySecret'   => 'test',
            'pathStyleEndpoint' => true,
        ]);

        $this->purge();

        SqsQueueFixture::inject($this->sqs, $this->queueUrl, waitTimeSeconds: 0);

        ResultLogMiddlewareFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->sqs)) {
            // Reading the queue sees only visible messages, so a delivery this
            // test left in flight would survive the drain and reappear in the
            // next one. Disconnecting hands it back first.
            SqsQueueFixture::disconnect();

            $this->purge();
        }

        SqsQueueFixture::reset();

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

        $received = SqsQueueFixture::receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());

        SqsQueueFixture::settle($received, JobResult::ACK, $client);
    }

    public function testAnAcknowledgedJobIsGoneForGood(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        SqsQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertNull($this->poll());
    }

    public function testARetriedJobIsRedeliveredByTheQueue(): void
    {
        // No ramp, so the retry sets a zero visibility timeout and the message
        // comes back at once. The hold itself is pinned by SqsQueueTest, which
        // needs no live broker to wait out.
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5, retryDelayMs: 0);

        $client = $this->client();
        $client->push($job);

        SqsQueueFixture::run(
            config: $this->config(),
            maxJobs: 1,
        );

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        // A processor-owned retry is not a re-publish, so the client is untouched
        self::assertCount(1, $client->getPushed());
        // The queue made it visible again rather than dropping it
        self::assertNotNull($this->poll());
    }

    public function testAnEmptyQueueYieldsNothing(): void
    {
        self::assertNull(SqsQueueFixture::receive());
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertNotNull(SqsQueueFixture::receive());

        // A worker shutting down mid-job must not make the queue wait out the
        // whole visibility timeout before another worker can take it
        SqsQueueFixture::disconnect();

        self::assertNotNull($this->poll());
    }

    /**
     * Poll on a re-established client, because a finished worker disconnects.
     */
    private function poll(): JobContract|null
    {
        SqsQueueFixture::inject($this->sqs, $this->queueUrl, waitTimeSeconds: 0);

        return SqsQueueFixture::receive();
    }

    private function client(): SqsClient
    {
        return new SqsClient(sqs: $this->sqs, queueUrl: $this->queueUrl);
    }

    private function config(): QueueConfigContract
    {
        return new SqsWorkerConfigFixture(
            sqsQueueUrl: $this->queueUrl,
            sqsEndpoint: $this->endpoint,
        );
    }

    /**
     * Empty the queue by reading it, rather than by purging it.
     *
     * SQS allows one `PurgeQueue` per queue per minute, and this file empties
     * the queue twice per test, so a purge would answer
     * `PurgeQueueInProgress` for every test after the first.
     *
     * A short poll samples a subset of hosts, so one empty response is not
     * proof the queue is empty. This stops on it anyway, because the drain's
     * job is to leave no delivery the next test would read, and a long poll per
     * round would cost more than it buys.
     */
    private function purge(): void
    {
        while (true) {
            $messages = $this->sqs->receiveMessage([
                'QueueUrl'            => $this->queueUrl,
                'MaxNumberOfMessages' => 10,
                'WaitTimeSeconds'     => 0,
            ])->getMessages();

            if ($messages === []) {
                return;
            }

            $deleted = 0;

            foreach ($messages as $message) {
                $handle = $message->getReceiptHandle();

                if ($handle !== null) {
                    $this->sqs->deleteMessage([
                        'QueueUrl'      => $this->queueUrl,
                        'ReceiptHandle' => $handle,
                    ]);

                    $deleted++;
                }
            }

            // A delivery with no handle cannot be deleted, so reading again
            // would return it for ever
            if ($deleted === 0) {
                return;
            }
        }
    }
}
