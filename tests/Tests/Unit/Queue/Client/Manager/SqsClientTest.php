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
use Valkyrja\Queue\Client\Manager\SqsClient;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Client\SqsFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_decode;

final class SqsClientTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string QUEUE_URL = 'https://sqs.us-east-1.amazonaws.com/1/default';

    protected SqsFixture $sqs;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->sqs = new SqsFixture();
    }

    public function testPushSendsTheEnvelopeToTheQueue(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $calls = $this->sqs->getCalls('sendMessage');

        self::assertCount(1, $calls);
        self::assertSame(self::QUEUE_URL, $calls[0]['QueueUrl']);

        /** @var array<string, mixed> $envelope */
        $envelope = json_decode($calls[0]['MessageBody'], true);

        self::assertSame(self::NAME, $envelope[EnvelopeField::NAME]);
        self::assertSame(['user_id' => 42], $envelope[EnvelopeField::PAYLOAD]);
        self::assertSame(1, $envelope[EnvelopeField::ATTEMPTS]);
    }

    public function testAnImmediateJobCarriesNoDelay(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME));

        self::assertSame(0, $this->sqs->getCalls('sendMessage')[0]['DelaySeconds']);
    }

    public function testTheProducersDelayIsConvertedToWholeSeconds(): void
    {
        $this->client()->push(new Job(name: self::NAME, delayMs: 5000));

        self::assertSame(5, $this->sqs->getCalls('sendMessage')[0]['DelaySeconds']);
    }

    public function testASubSecondDelayRoundsDownToZero(): void
    {
        // SQS has no finer grain than a second
        $this->client()->push(new Job(name: self::NAME, delayMs: 999));

        self::assertSame(0, $this->sqs->getCalls('sendMessage')[0]['DelaySeconds']);
    }

    public function testADelayBeyondTheProcessorsCeilingIsClamped(): void
    {
        $this->client()->push(new Job(name: self::NAME, delayMs: 3_600_000));

        self::assertSame(
            SqsClient::MAX_DELAY_SECONDS,
            $this->sqs->getCalls('sendMessage')[0]['DelaySeconds']
        );
    }

    public function testARetrySendsNothingBecauseTheProcessorOwnsRedelivery(): void
    {
        // The original delivery is still in flight; publishing would duplicate it
        $this->client()->retry(new Job(name: self::NAME, attempts: 2), 5000);

        self::assertSame([], $this->sqs->getCalls('sendMessage'));
    }

    protected function client(): SqsClient
    {
        return new SqsClient($this->sqs, self::QUEUE_URL);
    }
}
