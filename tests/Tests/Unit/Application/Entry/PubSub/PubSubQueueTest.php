<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry\PubSub;

use Google\ApiCore\ApiException;
use Google\Cloud\PubSub\Message;
use Google\Rpc\Code;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Application\Entry\PubSub\PubSubQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Data\QueuePubSubClientConfig;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Tests\Fixtures\Application\Entry\PubSubQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\PubSubSubscriptionFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class PubSubQueueTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    protected PubSubSubscriptionFixture $subscription;

    /**
     * @return array<string, array{JobResult}>
     */
    public static function terminalProvider(): array
    {
        return [
            'ack'         => [JobResult::ACK],
            'fail'        => [JobResult::FAIL],
            'dead letter' => [JobResult::DEAD_LETTER],
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subscription = new PubSubSubscriptionFixture();

        PubSubQueueFixture::inject($this->subscription, timeoutMs: 250);
    }

    #[Override]
    protected function tearDown(): void
    {
        PubSubQueueFixture::reset();

        parent::tearDown();
    }

    public function testTheSubscriptionNameDefaultsToTheTopic(): void
    {
        // A subscription usually carries the name of its topic
        self::assertSame(
            'jobs',
            PubSubQueueFixture::readSubscriptionName(new QueuePubSubClientConfig(pubSubTopic: 'jobs'))
        );
    }

    public function testAnApplicationCanNameTheSubscription(): void
    {
        PubSubQueueFixture::nameSubscription('jobs-worker');

        self::assertSame(
            'jobs-worker',
            PubSubQueueFixture::readSubscriptionName(new QueuePubSubClientConfig(pubSubTopic: 'jobs'))
        );
    }

    public function testConnectOpensTheSubscriptionFromTheConfig(): void
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn(new QueuePubSubClientConfig());

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        PubSubQueueFixture::connect($app);

        // The injected subscription survives connect, so the loop polls it
        self::assertNull(PubSubQueueFixture::receive());
    }

    public function testAnEmptySubscriptionYieldsNothing(): void
    {
        self::assertNull(PubSubQueueFixture::receive());
    }

    public function testAPullAsksForOneDeliveryWithinItsDeadline(): void
    {
        PubSubQueueFixture::receive();

        self::assertSame(
            [['maxMessages' => 1, 'timeoutMillis' => 250]],
            $this->subscription->pulls
        );
    }

    public function testATransportDeadlineReadsAsNothingArrived(): void
    {
        // The REST transport reports a passed deadline as a cURL timeout
        $this->subscription->failure = new ConnectException(
            'timed out',
            new Request('POST', '/'),
            null,
            ['errno' => PubSubQueue::CURL_OPERATION_TIMED_OUT]
        );

        self::assertNull(PubSubQueueFixture::receive());
    }

    public function testAConnectionFailureTravelsOn(): void
    {
        // A refused connection, a failed name lookup and a TLS failure all
        // arrive as a ConnectException too. The loop does not back off when a
        // receive gives nothing, so swallowing these would spin the worker.
        $this->subscription->failure = new ConnectException(
            'connection refused',
            new Request('POST', '/'),
            null,
            ['errno' => 7]
        );

        $this->expectException(ConnectException::class);

        PubSubQueueFixture::receive();
    }

    public function testATransportFailureWithNoErrorNumberTravelsOn(): void
    {
        $this->subscription->failure = new ConnectException('unknown', new Request('POST', '/'));

        $this->expectException(ConnectException::class);

        PubSubQueueFixture::receive();
    }

    public function testAnApiDeadlineReadsAsNothingArrived(): void
    {
        $this->subscription->failure = new ApiException(
            'deadline exceeded',
            Code::DEADLINE_EXCEEDED,
            'DEADLINE_EXCEEDED'
        );

        self::assertNull(PubSubQueueFixture::receive());
    }

    public function testAnyOtherApiFailureTravelsOn(): void
    {
        // A real failure must not be mistaken for an empty subscription
        $this->subscription->failure = new ApiException(
            'permission denied',
            Code::PERMISSION_DENIED,
            'PERMISSION_DENIED'
        );

        $this->expectException(ApiException::class);

        PubSubQueueFixture::receive();
    }

    public function testAReceivedDeliveryIsReadBackAsAJob(): void
    {
        $this->seed(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $job = PubSubQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->getAll());
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeAcknowledgesTheDelivery(JobResult $result): void
    {
        $this->received();

        PubSubQueueFixture::settle(new JobFactory()->create(self::NAME), $result, new InMemoryClient());

        self::assertCount(1, $this->subscription->acknowledged);
        self::assertSame([], $this->subscription->deadlines);
    }

    public function testARetryHoldsTheDeliveryForItsRamp(): void
    {
        $this->received();

        // The default ramp, so the delivery waits rather than coming straight
        // back and burning the next attempt at once
        PubSubQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::RETRY, new InMemoryClient());

        self::assertCount(1, $this->subscription->deadlines);
        self::assertSame(1, $this->subscription->deadlines[0][1]);
        self::assertSame([], $this->subscription->acknowledged);
    }

    public function testARetryWithNoRampComesBackAtOnce(): void
    {
        $this->received();

        // Zero is Pub/Sub's nack: available again, and the attempt count goes up
        PubSubQueueFixture::settle(
            new Job(name: self::NAME, retryDelayMs: 0),
            JobResult::RETRY,
            new InMemoryClient()
        );

        self::assertSame(0, $this->subscription->deadlines[0][1]);
    }

    public function testASubSecondRampHoldsForOneSecond(): void
    {
        $this->received();

        PubSubQueueFixture::settle(
            new Job(name: self::NAME, retryDelayMs: 500),
            JobResult::RETRY,
            new InMemoryClient()
        );

        self::assertSame(1, $this->subscription->deadlines[0][1]);
    }

    public function testARampLongerThanPubSubAllowsIsClamped(): void
    {
        $this->received();

        PubSubQueueFixture::settle(
            new Job(name: self::NAME, retryDelayMs: 9_000_000),
            JobResult::RETRY,
            new InMemoryClient()
        );

        self::assertSame(PubSubQueue::MAX_ACK_DEADLINE, $this->subscription->deadlines[0][1]);
    }

    public function testTheDeliveryAttemptBecomesTheJobAttempts(): void
    {
        // A processor-owned adapter never rewrites the envelope, so the count
        // Pub/Sub reports is the only thing that advances the attempt
        $this->subscription->next = [
            new Message(
                ['data' => new JobFactory()->toJson(new JobFactory()->create(self::NAME)), 'messageId' => 'm-1'],
                ['ackId' => 'ack-id-1', 'deliveryAttempt' => 3]
            ),
        ];

        $job = PubSubQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(3, $job->getAttempts());
    }

    public function testAnAbsentDeliveryAttemptKeepsTheEnvelopeCount(): void
    {
        // Pub/Sub reports the count only on a subscription with a dead-letter
        // policy, so without one the envelope's own count stands
        $this->seed(new JobFactory()->create(self::NAME));

        $job = PubSubQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(1, $job->getAttempts());
    }

    public function testAZeroDeliveryAttemptKeepsTheEnvelopeCount(): void
    {
        $this->subscription->next = [
            new Message(
                ['data' => new JobFactory()->toJson(new JobFactory()->create(self::NAME)), 'messageId' => 'm-1'],
                ['ackId' => 'ack-id-1', 'deliveryAttempt' => 0]
            ),
        ];

        $job = PubSubQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(1, $job->getAttempts());
    }

    public function testPullingWithoutAConnectionFails(): void
    {
        PubSubQueueFixture::reset();

        $this->expectException(QueueServerNotConnectedException::class);

        PubSubQueueFixture::receive();
    }

    public function testAnUnreadableEnvelopeIsAcknowledgedRatherThanRedelivered(): void
    {
        // Nothing settles a body the factory cannot read, so the subscription
        // would hand the same message back on every deadline
        $this->subscription->next = [
            new Message(['data' => '{not json', 'messageId' => 'message-id-1'], ['ackId' => 'ack-id-1']),
        ];

        self::assertNull(PubSubQueueFixture::receive());
        self::assertCount(1, $this->subscription->acknowledged);
    }

    public function testADeliveryWithNoBodyIsAcknowledged(): void
    {
        // Pub/Sub accepts a message with attributes and no data, and the factory
        // takes a string under strict types, so this would be a TypeError the
        // decode guard does not hold
        $this->subscription->next = [
            new Message(['messageId' => 'm-1'], ['ackId' => 'ack-id-1']),
        ];

        self::assertNull(PubSubQueueFixture::receive());
        self::assertCount(1, $this->subscription->acknowledged);
    }

    public function testAnEnvelopeThatCarriesNoObjectIsAcknowledged(): void
    {
        $this->subscription->next = [
            new Message(['data' => '5', 'messageId' => 'message-id-1'], ['ackId' => 'ack-id-1']),
        ];

        self::assertNull(PubSubQueueFixture::receive());
        self::assertCount(1, $this->subscription->acknowledged);
    }

    public function testAnUnreadableEnvelopeLeavesNothingInFlight(): void
    {
        $this->subscription->next = [
            new Message(['data' => '{not json', 'messageId' => 'message-id-1'], ['ackId' => 'ack-id-1']),
        ];

        PubSubQueueFixture::receive();
        PubSubQueueFixture::disconnect();

        // The acknowledgement already retired it, so a release would answer a
        // delivery that is already gone
        self::assertSame([], $this->subscription->deadlines);
    }

    public function testSettlingWithNothingInFlightDoesNothing(): void
    {
        PubSubQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->subscription->acknowledged);
        self::assertSame([], $this->subscription->deadlines);
    }

    public function testADeliveryIsSettledOnlyOnce(): void
    {
        $this->received();

        PubSubQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());
        PubSubQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->subscription->acknowledged);
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $this->received();

        // A worker shutting down mid-job must not make the subscription wait
        // out the whole acknowledgement deadline
        PubSubQueueFixture::disconnect();

        self::assertCount(1, $this->subscription->deadlines);
        self::assertSame(0, $this->subscription->deadlines[0][1]);
    }

    public function testDisconnectWithNothingInFlightHandsBackNothing(): void
    {
        PubSubQueueFixture::disconnect();

        self::assertSame([], $this->subscription->deadlines);
    }

    protected function seed(Job $job): void
    {
        $this->subscription->next = [
            new Message([
                'data'      => new JobFactory()->toJson($job),
                'messageId' => 'message-id-1',
            ], ['ackId' => 'ack-id-1']),
        ];
    }

    protected function received(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        PubSubQueueFixture::receive();
    }
}
