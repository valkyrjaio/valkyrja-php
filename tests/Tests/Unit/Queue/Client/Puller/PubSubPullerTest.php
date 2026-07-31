<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Puller;

use Google\ApiCore\ApiException;
use Google\Cloud\PubSub\Message;
use Google\Rpc\Code;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Puller\PubSubPuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Client\PubSubSubscriptionFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the PubSubPuller.
 */
final class PubSubPullerTest extends TestCase
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
    }

    public function testAnEmptySubscriptionYieldsNothing(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testAPullAsksForOneDeliveryWithinItsDeadline(): void
    {
        $this->puller()->receive();

        self::assertSame(
            [['maxMessages' => 1, 'timeoutMillis' => 250]],
            $this->subscription->pulls
        );
    }

    public function testATransportDeadlineReadsAsNothingArrived(): void
    {
        // The REST transport reports a passed deadline as a connection error
        $this->subscription->failure = new ConnectException('timed out', new Request('POST', '/'));

        self::assertNull($this->puller()->receive());
    }

    public function testAnApiDeadlineReadsAsNothingArrived(): void
    {
        $this->subscription->failure = new ApiException(
            'deadline exceeded',
            Code::DEADLINE_EXCEEDED,
            'DEADLINE_EXCEEDED'
        );

        self::assertNull($this->puller()->receive());
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

        $this->puller()->receive();
    }

    public function testAReceivedDeliveryIsReadBackAsAJob(): void
    {
        $this->seed(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->getAll());
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeAcknowledgesTheDelivery(JobResult $result): void
    {
        $puller = $this->received();

        $puller->settle(new JobFactory()->create(self::NAME), $result, new InMemoryClient());

        self::assertCount(1, $this->subscription->acknowledged);
        self::assertSame([], $this->subscription->deadlines);
    }

    public function testARetryShortensTheDeadlineSoTheDeliveryComesBack(): void
    {
        $puller = $this->received();

        $puller->settle(new JobFactory()->create(self::NAME), JobResult::RETRY, new InMemoryClient());

        self::assertCount(1, $this->subscription->deadlines);
        // Zero is Pub/Sub's nack: available again, and the attempt count goes up
        self::assertSame(0, $this->subscription->deadlines[0][1]);
        self::assertSame([], $this->subscription->acknowledged);
    }

    public function testSettlingWithNothingInFlightDoesNothing(): void
    {
        $this->puller()->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->subscription->acknowledged);
        self::assertSame([], $this->subscription->deadlines);
    }

    public function testADeliveryIsSettledOnlyOnce(): void
    {
        $puller = $this->received();

        $puller->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());
        $puller->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->subscription->acknowledged);
    }

    public function testDisconnectHandsAnInFlightDeliveryBack(): void
    {
        $puller = $this->received();

        // A worker shutting down mid-job must not make the subscription wait
        // out the whole acknowledgement deadline
        $puller->disconnect();

        self::assertCount(1, $this->subscription->deadlines);
        self::assertSame(0, $this->subscription->deadlines[0][1]);
    }

    public function testDisconnectWithNothingInFlightHandsBackNothing(): void
    {
        $puller = $this->puller();
        $puller->connect();
        $puller->disconnect();

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

    protected function received(): PubSubPuller
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $puller = $this->puller();
        $puller->receive();

        return $puller;
    }

    protected function puller(): PubSubPuller
    {
        return new PubSubPuller($this->subscription, timeoutMs: 250);
    }
}
