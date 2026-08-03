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

use Override;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Puller\AmqpPuller;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Tests\Fixtures\Queue\Client\AmqpChannelFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\AmqpPullerFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_encode;

/**
 * Test the AmqpPuller.
 */
final class AmqpPullerTest extends TestCase
{
    /** @var non-empty-string */
    protected const string QUEUE = 'queues.default';

    protected AmqpChannelFixture $channel;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new AmqpChannelFixture();
    }

    public function testConnectDeclaresTheQueueAndLimitsUnacknowledgedDeliveries(): void
    {
        $this->puller()->connect();

        self::assertCount(1, $this->channel->getCalls('queue_declare'));
        // One in flight at a time, matching the single delivery slot
        self::assertSame([[0, 1, false]], $this->channel->getCalls('basic_qos'));
    }

    public function testReceiveReturnsNullWhenNothingIsWaiting(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testReceiveDecodesTheEnvelope(): void
    {
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 3]);

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame('SendWelcomeEmail', $job->getName());
        self::assertSame(3, $job->getAttempts());
    }

    public function testAnAcknowledgedJobIsAcked(): void
    {
        $puller = $this->receiveOne();

        $puller->settle(new JobFactory()->create('x'), JobResult::ACK, new InMemoryClient());

        self::assertSame([['delivery-1', false]], $this->channel->getCalls('basic_ack'));
        self::assertSame([], $this->channel->getCalls('basic_nack'));
    }

    public function testARetryIsNackedBackOntoTheQueue(): void
    {
        $puller = $this->receiveOne();

        $puller->settle(new JobFactory()->create('x'), JobResult::RETRY, new InMemoryClient());

        // Requeued, so the broker redelivers and owns the attempt counting
        self::assertSame([['delivery-1', false, true]], $this->channel->getCalls('basic_nack'));
    }

    public function testAFailIsNackedWithoutRequeue(): void
    {
        $puller = $this->receiveOne();

        $puller->settle(new JobFactory()->create('x'), JobResult::FAIL, new InMemoryClient());

        self::assertSame([['delivery-1', false, false]], $this->channel->getCalls('basic_nack'));
    }

    public function testADeadLetterIsNackedWithoutRequeue(): void
    {
        $puller = $this->receiveOne();

        $puller->settle(new JobFactory()->create('x'), JobResult::DEAD_LETTER, new InMemoryClient());

        self::assertSame([['delivery-1', false, false]], $this->channel->getCalls('basic_nack'));
    }

    public function testSettlingWithNothingInFlightDoesNothing(): void
    {
        $this->puller()->settle(new JobFactory()->create('x'), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->channel->getCalls('basic_ack'));
        self::assertSame([], $this->channel->getCalls('basic_nack'));
    }

    public function testSettlingTwiceCannotDoubleAcknowledge(): void
    {
        $puller = $this->receiveOne();

        $puller->settle(new JobFactory()->create('x'), JobResult::ACK, new InMemoryClient());
        $puller->settle(new JobFactory()->create('x'), JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->channel->getCalls('basic_ack'));
    }

    public function testDisconnectReleasesAnInFlightDelivery(): void
    {
        $puller = $this->receiveOne();

        $puller->disconnect();

        // A shutdown did not complete the work, so hand it straight back
        self::assertSame([['delivery-1', false, true]], $this->channel->getCalls('basic_nack'));
        self::assertTrue($this->channel->closed);
    }

    public function testDisconnectWithNothingInFlightJustCloses(): void
    {
        $this->puller()->disconnect();

        self::assertSame([], $this->channel->getCalls('basic_nack'));
        self::assertTrue($this->channel->closed);
    }

    public function testAnEmptyPollYieldsForTheConfiguredTimeout(): void
    {
        // A polling consumer must yield, or the entry's loop bounds and
        // graceful shutdown would never get a chance to run
        $puller = new AmqpPullerFixture(channel: $this->channel, queue: self::QUEUE, timeout: 1);

        self::assertNull($puller->receive());
        self::assertSame(1, $puller->waits);
    }

    public function testAZeroTimeoutDoesNotYield(): void
    {
        $puller = new AmqpPullerFixture(channel: $this->channel, queue: self::QUEUE, timeout: 0);

        self::assertNull($puller->receive());
        self::assertSame(0, $puller->waits);
    }

    public function testAFirstDeliveryKeepsTheEnvelopeAttempt(): void
    {
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 1]);

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(1, $job->getAttempts());
    }

    public function testAQuorumQueueDeliveryCountBecomesTheAttempt(): void
    {
        // The header counts redeliveries, so the attempt is one more than it.
        // Without this the envelope attempt never advances and max_attempts
        // can never stop a failing chain.
        $this->channel->next = $this->delivery(
            ['name' => 'SendWelcomeEmail', 'attempts' => 1],
            headers: [AmqpPuller::DELIVERY_COUNT_HEADER => 4],
        );

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(5, $job->getAttempts());
    }

    public function testARedeliveredClassicDeliveryAdvancesPastTheFirstAttempt(): void
    {
        // A classic queue reports only a flag, so the adapter can say the
        // delivery is not the first, and no more than that
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 1], redelivered: true);

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(2, $job->getAttempts());
    }

    public function testARedeliveredFlagNeverLowersTheEnvelopeAttempt(): void
    {
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 4], redelivered: true);

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(4, $job->getAttempts());
    }

    public function testARawHeaderTableIsRead(): void
    {
        // php-amqplib keeps a header array as an array rather than wrapping it
        $this->channel->next = $this->rawHeaderDelivery([AmqpPuller::DELIVERY_COUNT_HEADER => 2]);

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(3, $job->getAttempts());
    }

    public function testANonTableHeaderValueIsIgnored(): void
    {
        $this->channel->next = $this->rawHeaderDelivery('not-a-table');

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(1, $job->getAttempts());
    }

    public function testANegativeDeliveryCountIsIgnored(): void
    {
        // A count below zero would make the attempt zero, which the envelope
        // rejects, so a malformed header would crash the puller
        $this->channel->next = $this->delivery(
            ['name' => 'SendWelcomeEmail', 'attempts' => 3],
            headers: [AmqpPuller::DELIVERY_COUNT_HEADER => -1],
        );

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(3, $job->getAttempts());
    }

    public function testAMalformedDeliveryCountIsIgnored(): void
    {
        $this->channel->next = $this->delivery(
            ['name' => 'SendWelcomeEmail', 'attempts' => 3],
            headers: [AmqpPuller::DELIVERY_COUNT_HEADER => 'not-a-count'],
        );

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(3, $job->getAttempts());
    }

    /**
     * Receive a single scripted delivery and return the puller holding it.
     */
    protected function receiveOne(): AmqpPuller
    {
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail']);

        $puller = $this->puller();
        $puller->receive();

        return $puller;
    }

    /**
     * Build a delivery whose headers are set without an AMQP table wrapper.
     */
    protected function rawHeaderDelivery(mixed $headers): AMQPMessage
    {
        $message = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 1]);

        $message->set('application_headers', $headers);

        return $message;
    }

    /**
     * Build a delivery whose ack and nack route back to the fixture channel.
     *
     * @param array<non-empty-string, mixed> $envelope    The envelope
     * @param array<non-empty-string, mixed> $headers     The AMQP headers
     * @param bool                           $redelivered Whether the broker marks this a redelivery
     */
    protected function delivery(array $envelope, array $headers = [], bool $redelivered = false): AMQPMessage
    {
        $properties = $headers !== []
            ? ['application_headers' => new AMQPTable($headers)]
            : [];

        $message = new AMQPMessage(
            (string) json_encode([EnvelopeField::NAME => 'x', ...$envelope]),
            $properties
        );

        $message->setChannel($this->channel);
        $message->setDeliveryInfo('delivery-1', $redelivered, '', self::QUEUE);

        return $message;
    }

    protected function puller(): AmqpPuller
    {
        return new AmqpPuller(channel: $this->channel, queue: self::QUEUE, timeout: 0);
    }
}
