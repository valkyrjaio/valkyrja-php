<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry\Amqp;

use Override;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Valkyrja\Application\Entry\Amqp\AmqpQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Data\QueueAmqpClientConfig;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Tests\Fixtures\Application\Entry\AmqpQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\AmqpChannelFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\AmqpConnectionFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_encode;

final class AmqpQueueTest extends TestCase
{
    /** @var non-empty-string */
    protected const string QUEUE = 'queues.default';

    protected AmqpChannelFixture $channel;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new AmqpChannelFixture();

        AmqpQueueFixture::inject($this->channel, self::QUEUE, timeout: 0);
    }

    #[Override]
    protected function tearDown(): void
    {
        AmqpQueueFixture::reset();

        parent::tearDown();
    }

    public function testConnectDeclaresTheQueueAndSetsThePrefetch(): void
    {
        AmqpQueueFixture::connect($this->application());

        self::assertCount(1, $this->channel->getCalls('queue_declare'));
        // Set for the day the entry registers a consumer; the broker ignores it
        // for the basic_get this entry fetches with
        self::assertSame([[0, 1, false]], $this->channel->getCalls('basic_qos'));
    }

    public function testPollingWithoutAChannelFails(): void
    {
        AmqpQueueFixture::reset();

        $this->expectException(QueueServerNotConnectedException::class);

        AmqpQueueFixture::receive();
    }

    public function testAHeaderTableWithoutTheCountIsIgnored(): void
    {
        $this->channel->next = $this->delivery(
            ['name' => 'SendWelcomeEmail', 'attempts' => 2],
            headers: ['x-something-else' => 'value'],
        );

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        // The table is present and is an array, but carries no count
        self::assertSame(2, $job->getAttempts());
    }

    public function testAnUnreadableBodyIsRejectedWithoutRequeue(): void
    {
        $message = new AMQPMessage('not json at all');
        $message->setChannel($this->channel);
        $message->setDeliveryInfo('delivery-1', false, '', self::QUEUE);

        $this->channel->next = $message;

        self::assertNull(AmqpQueueFixture::receive());

        // Handing it back would give the next worker the same body for ever
        self::assertSame([['delivery-1', false, false]], $this->channel->getCalls('basic_nack'));
    }

    public function testReceiveReturnsNullWhenNothingIsWaiting(): void
    {
        self::assertNull(AmqpQueueFixture::receive());
    }

    public function testReceiveDecodesTheEnvelope(): void
    {
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 3]);

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame('SendWelcomeEmail', $job->getName());
        self::assertSame(3, $job->getAttempts());
    }

    public function testAnAcknowledgedJobIsAcked(): void
    {
        $this->receiveOne();

        AmqpQueueFixture::settle(new JobFactory()->create('x'), JobResult::ACK, new InMemoryClient());

        self::assertSame([['delivery-1', false]], $this->channel->getCalls('basic_ack'));
        self::assertSame([], $this->channel->getCalls('basic_nack'));
    }

    public function testARetryIsNackedBackOntoTheQueue(): void
    {
        $this->receiveOne();

        AmqpQueueFixture::settle(new JobFactory()->create('x'), JobResult::RETRY, new InMemoryClient());

        // Requeued, so the broker redelivers and owns the attempt counting
        self::assertSame([['delivery-1', false, true]], $this->channel->getCalls('basic_nack'));
    }

    public function testAFailIsNackedWithoutRequeue(): void
    {
        $this->receiveOne();

        AmqpQueueFixture::settle(new JobFactory()->create('x'), JobResult::FAIL, new InMemoryClient());

        self::assertSame([['delivery-1', false, false]], $this->channel->getCalls('basic_nack'));
    }

    public function testADeadLetterIsNackedWithoutRequeue(): void
    {
        $this->receiveOne();

        AmqpQueueFixture::settle(new JobFactory()->create('x'), JobResult::DEAD_LETTER, new InMemoryClient());

        self::assertSame([['delivery-1', false, false]], $this->channel->getCalls('basic_nack'));
    }

    public function testSettlingWithNothingInFlightDoesNothing(): void
    {
        AmqpQueueFixture::settle(new JobFactory()->create('x'), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->channel->getCalls('basic_ack'));
        self::assertSame([], $this->channel->getCalls('basic_nack'));
    }

    public function testSettlingTwiceCannotDoubleAcknowledge(): void
    {
        $this->receiveOne();

        AmqpQueueFixture::settle(new JobFactory()->create('x'), JobResult::ACK, new InMemoryClient());
        AmqpQueueFixture::settle(new JobFactory()->create('x'), JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->channel->getCalls('basic_ack'));
    }

    public function testDisconnectReleasesAnInFlightDelivery(): void
    {
        $this->receiveOne();

        AmqpQueueFixture::disconnect();

        // A shutdown did not complete the work, so hand it straight back
        self::assertSame([['delivery-1', false, true]], $this->channel->getCalls('basic_nack'));
        self::assertTrue($this->channel->closed);
    }

    public function testDisconnectWithNothingInFlightJustCloses(): void
    {
        AmqpQueueFixture::disconnect();

        self::assertSame([], $this->channel->getCalls('basic_nack'));
        self::assertTrue($this->channel->closed);
    }

    public function testDisconnectClosesAConnectionTheEntryOpened(): void
    {
        $connection = new AmqpConnectionFixture();

        AmqpQueueFixture::opened($connection);
        AmqpQueueFixture::disconnect();

        // Closing the channel alone leaves the socket open, so a process that
        // runs the loop twice would hold a second broker connection
        self::assertTrue($this->channel->closed);
        self::assertTrue($connection->closed);
    }

    public function testDisconnectLeavesAConnectionTheCallerOwns(): void
    {
        // A caller that handed over a channel still needs the connection under
        // it, so the entry closes only what it opened
        $connection = new AmqpConnectionFixture();

        AmqpQueueFixture::disconnect();

        self::assertTrue($this->channel->closed);
        self::assertFalse($connection->closed);
    }

    public function testAnEmptyPollYieldsForTheConfiguredTimeout(): void
    {
        // A polling consumer must yield, or the entry's loop bounds and
        // graceful shutdown would never get a chance to run
        AmqpQueueFixture::inject($this->channel, self::QUEUE, timeout: 1);

        self::assertNull(AmqpQueueFixture::receive());
        self::assertSame(1, AmqpQueueFixture::$waits);
    }

    public function testAZeroTimeoutDoesNotYield(): void
    {
        AmqpQueueFixture::inject($this->channel, self::QUEUE, timeout: 0);

        self::assertNull(AmqpQueueFixture::receive());
        self::assertSame(0, AmqpQueueFixture::$waits);
    }

    public function testAFirstDeliveryKeepsTheEnvelopeAttempt(): void
    {
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 1]);

        $job = AmqpQueueFixture::receive();

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
            headers: [AmqpQueue::DELIVERY_COUNT_HEADER => 4],
        );

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(5, $job->getAttempts());
    }

    public function testARedeliveredClassicDeliveryAdvancesPastTheFirstAttempt(): void
    {
        // A classic queue reports only a flag, so the adapter can say the
        // delivery is not the first, and no more than that
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 1], redelivered: true);

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(2, $job->getAttempts());
    }

    public function testARedeliveredFlagNeverLowersTheEnvelopeAttempt(): void
    {
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail', 'attempts' => 4], redelivered: true);

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(4, $job->getAttempts());
    }

    public function testARawHeaderTableIsRead(): void
    {
        // php-amqplib keeps a header array as an array rather than wrapping it
        $this->channel->next = $this->rawHeaderDelivery([AmqpQueue::DELIVERY_COUNT_HEADER => 2]);

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(3, $job->getAttempts());
    }

    public function testANonTableHeaderValueIsIgnored(): void
    {
        $this->channel->next = $this->rawHeaderDelivery('not-a-table');

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(1, $job->getAttempts());
    }

    public function testANegativeDeliveryCountIsIgnored(): void
    {
        // A count below zero would make the attempt zero, which the envelope
        // rejects, so a malformed header would crash the consumer
        $this->channel->next = $this->delivery(
            ['name' => 'SendWelcomeEmail', 'attempts' => 3],
            headers: [AmqpQueue::DELIVERY_COUNT_HEADER => -1],
        );

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(3, $job->getAttempts());
    }

    public function testAMalformedDeliveryCountIsIgnored(): void
    {
        $this->channel->next = $this->delivery(
            ['name' => 'SendWelcomeEmail', 'attempts' => 3],
            headers: [AmqpQueue::DELIVERY_COUNT_HEADER => 'not-a-count'],
        );

        $job = AmqpQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(3, $job->getAttempts());
    }

    /**
     * Build an application whose container carries the AMQP client config.
     */
    protected function application(): ApplicationContract
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn(new QueueAmqpClientConfig());

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        return $app;
    }

    /**
     * Receive a single scripted delivery, leaving it in flight.
     */
    protected function receiveOne(): void
    {
        $this->channel->next = $this->delivery(['name' => 'SendWelcomeEmail']);

        AmqpQueueFixture::receive();
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
}
