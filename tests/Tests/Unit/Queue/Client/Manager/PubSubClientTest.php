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
use Valkyrja\Queue\Client\Manager\PubSubClient;
use Valkyrja\Queue\Client\Throwable\Exception\QueueClientRedeliveryNotSupportedException;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Client\PubSubTopicFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_decode;

final class PubSubClientTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    protected PubSubTopicFixture $topic;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->topic = new PubSubTopicFixture();
    }

    public function testPushPublishesTheEnvelopeToTheTopic(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        self::assertCount(1, $this->topic->published);

        /** @var array<string, mixed> $message */
        $message = $this->topic->published[0];

        /** @var array<string, mixed> $envelope */
        $envelope = json_decode((string) $message['data'], true);

        self::assertSame(self::NAME, $envelope[EnvelopeField::NAME]);
        self::assertSame(['user_id' => 42], $envelope[EnvelopeField::PAYLOAD]);
        self::assertSame(1, $envelope[EnvelopeField::ATTEMPTS]);
    }

    public function testTheJobNameAndIdTravelAsAttributes(): void
    {
        // A subscription filter can route on these without reading the body
        $job = new Job(name: self::NAME, id: 'stable-id');

        $this->client()->push($job);

        /** @var array<string, mixed> $message */
        $message = $this->topic->published[0];

        self::assertSame(
            [
                EnvelopeField::NAME => self::NAME,
                EnvelopeField::ID   => 'stable-id',
            ],
            $message['attributes']
        );
    }

    public function testARetryIsRefusedBecauseTheProcessorOwnsRedelivery(): void
    {
        // The original delivery is still unacknowledged, so publishing would
        // duplicate it. Answering quietly is worse than refusing: the caller
        // reads a successful requeue and the job is in `getPushed()`, while
        // nothing ever reached the topic
        $this->expectException(QueueClientRedeliveryNotSupportedException::class);

        $this->client()->retry(new Job(name: self::NAME, attempts: 2), 5000);
    }

    public function testARefusedRetryRecordsNoPush(): void
    {
        try {
            $this->client()->retry(new Job(name: self::NAME, attempts: 2), 5000);
        } catch (QueueClientRedeliveryNotSupportedException) {
            // The refusal is asserted above; this pins what it leaves behind
        }

        self::assertSame([], $this->topic->published);
    }

    protected function client(): PubSubClient
    {
        return new PubSubClient($this->topic);
    }
}
