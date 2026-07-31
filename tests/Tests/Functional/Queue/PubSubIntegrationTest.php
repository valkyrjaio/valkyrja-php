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
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\PullQueue;
use Valkyrja\Queue\Client\Manager\PubSubClient;
use Valkyrja\Queue\Client\Puller\PubSubPuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_string;
use function preg_replace;
use function strtolower;

/**
 * Exercise the Pub/Sub processor against a real endpoint.
 *
 * Pub/Sub owns redelivery through the acknowledgement deadline, so a retry is a
 * deadline change rather than a fresh publish. These tests prove that the
 * subscription redelivers a nacked message, that an acknowledged one is gone
 * for good, and that the envelope survives the round trip — none of which a
 * recording double can tell you.
 *
 * `PUBSUB_EMULATOR_HOST` points the client at the Google Cloud Pub/Sub
 * emulator, which needs no credentials.
 */
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
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->subscription)) {
            $this->subscription->delete();
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

    public function testANackedJobIsRedeliveredBySubscription(): void
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
        self::assertNotNull($this->redelivered());
    }

    public function testAnEmptySubscriptionYieldsNothing(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $this->client()->push(new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK));

        $puller = $this->puller();
        $puller->connect();

        self::assertNotNull($puller->receive());

        // A worker shutting down mid-job must not make the subscription wait
        // out the whole acknowledgement deadline
        $puller->disconnect();

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
        $puller = $this->puller();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $job = $puller->receive();

            if ($job !== null) {
                return $job;
            }
        }

        return null;
    }

    private function client(): PubSubClient
    {
        return new PubSubClient(topic: $this->topic);
    }

    private function puller(): PubSubPuller
    {
        return new PubSubPuller(subscription: $this->subscription, timeoutMs: 1000);
    }

    private function config(): QueueConfigContract
    {
        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }
}
