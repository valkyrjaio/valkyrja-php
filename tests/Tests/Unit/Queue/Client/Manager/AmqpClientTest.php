<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Client\Manager;

use Override;
use PhpAmqpLib\Message\AMQPMessage;
use Valkyrja\Queue\Client\Manager\AmqpClient;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Queue\Client\AmqpChannelFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_decode;

final class AmqpClientTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string QUEUE = 'queues.default';

    protected AmqpChannelFixture $channel;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(1768564798.0);

        $this->channel = new AmqpChannelFixture();
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testDeclareQueueDeclaresADurableQueue(): void
    {
        $this->client()->declareQueue();

        $calls = $this->channel->getCalls('queue_declare');

        self::assertCount(1, $calls);
        self::assertSame(self::QUEUE, $calls[0][0]);
        // Durable, so a broker restart does not lose the queue
        self::assertTrue($calls[0][2]);
    }

    public function testPushPublishesThePersistentEnvelope(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $calls = $this->channel->getCalls('basic_publish');

        self::assertCount(1, $calls);

        $message = $calls[0][0];

        self::assertInstanceOf(AMQPMessage::class, $message);
        self::assertSame('', $calls[0][1]);
        self::assertSame(self::QUEUE, $calls[0][2]);
        self::assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $message->get('delivery_mode'));
        self::assertSame('application/json', $message->get('content_type'));

        /** @var array<string, mixed> $envelope */
        $envelope = json_decode($message->getBody(), true);

        self::assertSame(self::NAME, $envelope[EnvelopeField::NAME]);
        self::assertSame(['user_id' => 42], $envelope[EnvelopeField::PAYLOAD]);
    }

    public function testPushRoutesThroughTheConfiguredExchange(): void
    {
        new AmqpClient(channel: $this->channel, queue: 'other', exchange: 'jobs')
            ->push(new JobFactory()->create(self::NAME));

        $calls = $this->channel->getCalls('basic_publish');

        self::assertSame('jobs', $calls[0][1]);
        self::assertSame('other', $calls[0][2]);
    }

    public function testAPriorityIsCarriedOntoTheMessage(): void
    {
        $this->client()->push(new Job(name: self::NAME, priority: 7));

        self::assertSame(7, $this->channel->getCalls('basic_publish')[0][0]->get('priority'));
    }

    public function testANegativePriorityIsClampedToZero(): void
    {
        $this->client()->push(new Job(name: self::NAME, priority: -5));

        self::assertSame(0, $this->channel->getCalls('basic_publish')[0][0]->get('priority'));
    }

    public function testAnOversizedPriorityIsClampedToTheByteCeiling(): void
    {
        // AMQP carries priority in a single unsigned byte
        $this->client()->push(new Job(name: self::NAME, priority: 1000));

        self::assertSame(255, $this->channel->getCalls('basic_publish')[0][0]->get('priority'));
    }

    public function testRetryPublishesNothing(): void
    {
        $this->client()->retry(new Job(name: self::NAME, attempts: 2));

        // The broker still holds the unacknowledged delivery, so publishing
        // again would duplicate the job rather than retry it
        self::assertSame([], $this->channel->getCalls('basic_publish'));
    }

    public function testRetryStillRecordsTheHandover(): void
    {
        $client = $this->client();
        $client->retry(new Job(name: self::NAME, attempts: 2));

        self::assertCount(1, $client->getPushed());
    }

    protected function client(): AmqpClient
    {
        return new AmqpClient(channel: $this->channel, queue: self::QUEUE);
    }
}
