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

use AsyncAws\Sqs\ValueObject\Message;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Puller\SqsPuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Client\SqsFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the SqsPuller.
 */
final class SqsPullerTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string QUEUE_URL = 'https://sqs.us-east-1.amazonaws.com/1/default';

    /** @var non-empty-string */
    protected const string HANDLE = 'receipt-handle-1';

    protected SqsFixture $sqs;

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

        $this->sqs = new SqsFixture();
    }

    public function testAnEmptyQueueYieldsNothing(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testReceiveLongPollsWithTheConfiguredWait(): void
    {
        $this->puller()->receive();

        $input = $this->sqs->getCalls('receiveMessage')[0];

        self::assertSame(self::QUEUE_URL, $input['QueueUrl']);
        self::assertSame(1, $input['MaxNumberOfMessages']);
        self::assertSame(2, $input['WaitTimeSeconds']);
        self::assertSame(45, $input['VisibilityTimeout']);
    }

    public function testAReceivedDeliveryIsReadBackAsAJob(): void
    {
        $this->seed(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->getAll());
    }

    public function testADeliveryWithNoBodyIsSkipped(): void
    {
        // SQS types the body as optional, so a body-less delivery is reachable
        $this->sqs->next = [new Message(['MessageId' => 'id-1', 'ReceiptHandle' => self::HANDLE])];

        self::assertNull($this->puller()->receive());
    }

    public function testADeliveryWithNoReceiptHandleIsSkipped(): void
    {
        // SQS types the receipt handle as optional too. Without one the
        // delivery can be neither deleted nor released, so running it would
        // leave SQS redelivering the same message on every visibility timeout
        $this->sqs->next = [
            new Message([
                'MessageId' => 'id-1',
                'Body'      => new JobFactory()->toJson(new JobFactory()->create(self::NAME)),
            ]),
        ];

        $puller = $this->puller();

        self::assertNull($puller->receive());

        // Nothing is in flight, so a later settle must not touch the queue
        $puller->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->sqs->getCalls('deleteMessage'));
        self::assertSame([], $this->sqs->getCalls('changeMessageVisibility'));
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeDeletesTheDelivery(JobResult $result): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $puller = $this->puller();
        $job    = $puller->receive();

        self::assertNotNull($job);

        $puller->settle($job, $result, new InMemoryClient());

        $calls = $this->sqs->getCalls('deleteMessage');

        self::assertCount(1, $calls);
        self::assertSame(self::HANDLE, $calls[0]['ReceiptHandle']);
        self::assertSame([], $this->sqs->getCalls('changeMessageVisibility'));
    }

    public function testARetryMakesTheDeliveryVisibleAgain(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $puller = $this->puller();
        $job    = $puller->receive();

        self::assertNotNull($job);

        $puller->settle($job, JobResult::RETRY, new InMemoryClient());

        $calls = $this->sqs->getCalls('changeMessageVisibility');

        self::assertCount(1, $calls);
        self::assertSame(self::HANDLE, $calls[0]['ReceiptHandle']);
        // Zero: SQS redelivers at once and counts the receive
        self::assertSame(0, $calls[0]['VisibilityTimeout']);
        self::assertSame([], $this->sqs->getCalls('deleteMessage'));
    }

    public function testSettlingWithNothingInFlightDoesNothing(): void
    {
        $this->puller()->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->sqs->getCalls('deleteMessage'));
        self::assertSame([], $this->sqs->getCalls('changeMessageVisibility'));
    }

    public function testADeliveryIsSettledOnlyOnce(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $puller = $this->puller();
        $job    = $puller->receive();

        self::assertNotNull($job);

        $puller->settle($job, JobResult::ACK, new InMemoryClient());
        $puller->settle($job, JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->sqs->getCalls('deleteMessage'));
    }

    public function testDisconnectReleasesAnInFlightDelivery(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $puller = $this->puller();
        $puller->connect();
        $puller->receive();
        $puller->disconnect();

        $calls = $this->sqs->getCalls('changeMessageVisibility');

        self::assertCount(1, $calls);
        self::assertSame(0, $calls[0]['VisibilityTimeout']);
    }

    public function testDisconnectWithNothingInFlightReleasesNothing(): void
    {
        $puller = $this->puller();
        $puller->connect();
        $puller->disconnect();

        self::assertSame([], $this->sqs->getCalls('changeMessageVisibility'));
    }

    protected function seed(Job $job): void
    {
        $this->sqs->next = [
            new Message([
                'MessageId'     => 'id-1',
                'ReceiptHandle' => self::HANDLE,
                'Body'          => new JobFactory()->toJson($job),
            ]),
        ];
    }

    protected function puller(): SqsPuller
    {
        return new SqsPuller($this->sqs, self::QUEUE_URL, waitTimeSeconds: 2, visibilityTimeout: 45);
    }
}
