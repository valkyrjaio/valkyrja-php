<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Valkyrja\Tests\Functional\Queue;

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
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Provider\QueueTestComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function class_exists;
use function getenv;
use function is_string;

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
    private const string TOPIC = 'valkyrja-tests';

    /** @var non-empty-string */
    private const string SUBSCRIPTION = 'valkyrja-tests-sub';

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

        $pubSub = new PubSub(['projectId' => 'valkyrja-tests', 'transport' => 'rest']);

        $this->topic = $pubSub->topic(self::TOPIC);

        if (! $this->topic->exists()) {
            $this->topic = $pubSub->createTopic(self::TOPIC);
        }

        $this->subscription = $pubSub->subscription(self::SUBSCRIPTION);

        if (! $this->subscription->exists()) {
            $this->subscription = $this->topic->subscribe(self::SUBSCRIPTION);
        }

        $this->drain();

        ResultLogMiddlewareFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->subscription)) {
            $this->drain();
        }

        ResultLogMiddlewareFixture::reset();

        parent::tearDown();
    }

    public function testAPublishedJobRoundTripsThroughTheTopicUnchanged(): void
    {
        $job = new Job(
            name: QueueRoutingProviderFixture::ALWAYS_ACK,
            payload: Job::create('x', ['user_id' => 42, 'nested' => ['a' => 1]])->getPayload(),
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
        self::assertSame($client->getPushed()[0]->toArray(), $received->toArray());

        $puller->settle($received, JobResult::ACK, $client);
    }

    public function testAnAcknowledgedJobIsGoneForGood(): void
    {
        $job = Job::create(QueueRoutingProviderFixture::ALWAYS_ACK);

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
        self::assertNotNull($this->puller()->receive());
    }

    public function testAnEmptySubscriptionYieldsNothing(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $this->client()->push(Job::create(QueueRoutingProviderFixture::ALWAYS_ACK));

        $puller = $this->puller();
        $puller->connect();

        self::assertNotNull($puller->receive());

        // A worker shutting down mid-job must not make the subscription wait
        // out the whole acknowledgement deadline
        $puller->disconnect();

        self::assertNotNull($this->puller()->receive());
    }

    private function client(): PubSubClient
    {
        return new PubSubClient(topic: $this->topic);
    }

    private function puller(): PubSubPuller
    {
        return new PubSubPuller(subscription: $this->subscription);
    }

    private function config(): QueueConfigContract
    {
        return new QueueConfig(
            dir: Directory::$basePath,
            providers: [new QueueTestComponentProviderFixture()],
            resultSettledMiddleware: [ResultLogMiddlewareFixture::class],
        );
    }

    /**
     * Acknowledge everything waiting, so one test cannot see another's
     * leftovers.
     */
    private function drain(): void
    {
        foreach ($this->subscription->pull(['maxMessages' => 100]) as $message) {
            $this->subscription->acknowledge($message);
        }
    }
}
