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

use Google\Auth\Credentials\InsecureCredentials;
use Google\Cloud\PubSub\PubSubClient as PubSub;
use Google\Cloud\PubSub\Subscription;
use Google\Cloud\PubSub\Topic;
use Override;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\PubSubClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Application\Data\PubSubWorkerConfigFixture;
use Valkyrja\Tests\Fixtures\Application\Entry\PubSubQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_string;
use function preg_replace;
use function strtolower;

final class PubSubIntegrationTest extends TestCase
{
    /** @var non-empty-string */
    private const string PREFIX = 'valkyrja-tests-';

    private Topic $topic;

    private Subscription $subscription;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $host = getenv('PUBSUB_EMULATOR_HOST');

        if (! is_string($host) || $host === '') {
            self::markTestSkipped('Set PUBSUB_EMULATOR_HOST to a reachable Pub/Sub emulator to run this test.');
        }

        if (! class_exists(PubSub::class)) {
            self::markTestSkipped('The google/cloud-pubsub package is not installed.');
        }

        // The emulator has no credentials, so they are supplied explicitly: the
        // library only skips them for a gRPC transport, and this uses REST
        $pubSub = new PubSub([
            'projectId'   => 'valkyrja-tests',
            'transport'   => 'rest',
            'apiEndpoint' => $host,
            'credentials' => new InsecureCredentials(),
        ]);

        // A topic and a subscription per test: Pub/Sub redelivers a nacked
        // message on its own schedule, so a shared subscription would let one
        // test's leftovers reach the next
        $name = self::PREFIX . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $this->name()) ?? 'x');

        $this->topic        = $pubSub->createTopic($name);
        $this->subscription = $this->topic->subscribe($name . '-sub');

        ResultLogMiddlewareFixture::reset();

        PubSubQueueFixture::inject($this->subscription, timeoutMs: 1000);
    }

    #[Override]
    protected function tearDown(): void
    {
        PubSubQueueFixture::reset();

        // Guarded separately: a failed subscribe leaves the topic behind, and
        // the name repeats on every later run, so the next one cannot create it
        if (isset($this->subscription)) {
            $this->subscription->delete();
        }

        if (isset($this->topic)) {
            $this->topic->delete();
        }

        ResultLogMiddlewareFixture::reset();

        parent::tearDown();
    }

    public function testAPublishedJobRoundTripsThroughTheTopicUnchanged(): void
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

        $received = PubSubQueueFixture::receive();

        self::assertNotNull($received);
        // The envelope is the cross-language contract, so every field must survive
        self::assertSame($client->getPushed()[0]->asArray(), $received->asArray());

        PubSubQueueFixture::settle($received, JobResult::ACK, $client);
    }

    public function testAnAcknowledgedJobIsGoneForGood(): void
    {
        $job = new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK);

        $client = $this->client();
        $client->push($job);

        $this->work($client);

        self::assertSame([JobResult::ACK], ResultLogMiddlewareFixture::getResults($job->getId()));
        self::assertNull($this->poll());
    }

    public function testANackedJobIsRedeliveredBySubscription(): void
    {
        $job = new Job(name: QueueRoutingProviderFixture::ALWAYS_RETRY, maxAttempts: 5);

        $client = $this->client();
        $client->push($job);

        $this->work($client);

        self::assertSame([JobResult::RETRY], ResultLogMiddlewareFixture::getResults($job->getId()));
        // A processor-owned retry is not a re-publish, so the worker enqueued
        // nothing of its own. The record is empty rather than holding the push
        // above, because the worker ends the unit of work with each job.
        self::assertSame([], $client->getPushed());
        self::assertNotNull($this->redelivered());
    }

    public function testAnEmptySubscriptionYieldsNothing(): void
    {
        self::assertNull($this->poll());
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        self::assertNotNull(PubSubQueueFixture::receive());

        // A worker shutting down mid-job must not make the subscription wait
        // out the whole acknowledgement deadline
        PubSubQueueFixture::disconnect();

        self::assertNotNull($this->redelivered());
    }

    /**
     * Wait for a nacked message to come back.
     *
     * Pub/Sub makes a nacked message available again on its own schedule, so a
     * single pull is not enough to say it was dropped.
     */
    private function redelivered(): JobContract|null
    {
        PubSubQueueFixture::inject($this->subscription, timeoutMs: 1000);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $job = PubSubQueueFixture::receive();

            if ($job !== null) {
                return $job;
            }
        }

        return null;
    }

    /**
     * Run one job through a worker whose client publishes to the test topic.
     */
    private function work(PubSubClient $client): void
    {
        $app = PubSubQueueFixture::bootstrap($this->config());

        $app->getContainer()->setSingleton(ClientContract::class, $client);

        PubSubQueueFixture::loop($app, maxJobs: 1);
    }

    /**
     * Pull on a re-established subscription, because a finished worker disconnects.
     */
    private function poll(): JobContract|null
    {
        PubSubQueueFixture::inject($this->subscription, timeoutMs: 1000);

        return PubSubQueueFixture::receive();
    }

    private function client(): PubSubClient
    {
        return new PubSubClient(topic: $this->topic);
    }

    private function config(): QueueConfigContract
    {
        return new PubSubWorkerConfigFixture();
    }
}
