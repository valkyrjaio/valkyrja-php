<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry\Sqs;

use AsyncAws\Sqs\ValueObject\Message;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Application\Entry\Sqs\SqsQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Data\QueueSqsClientConfig;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Tests\Fixtures\Application\Entry\SqsQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\SqsFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class SqsQueueTest extends TestCase
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

        SqsQueueFixture::inject($this->sqs, self::QUEUE_URL);
    }

    #[Override]
    protected function tearDown(): void
    {
        SqsQueueFixture::reset();

        parent::tearDown();
    }

    public function testAnEmptyQueueYieldsNothing(): void
    {
        self::assertNull(SqsQueueFixture::receive());
    }

    public function testReceiveLongPollsWithTheConfiguredWait(): void
    {
        SqsQueueFixture::receive();

        $input = $this->sqs->getCalls('receiveMessage')[0];

        self::assertSame(self::QUEUE_URL, $input['QueueUrl']);
        self::assertSame(1, $input['MaxNumberOfMessages']);
        self::assertSame(2, $input['WaitTimeSeconds']);
        // The queue's own ownership window stands, so the receive names none
        self::assertArrayNotHasKey('VisibilityTimeout', $input);
    }

    public function testAReceivedDeliveryIsReadBackAsAJob(): void
    {
        $this->seed(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $job = SqsQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->getAll());
    }

    public function testADeliveryWithNoBodyIsRetired(): void
    {
        // SQS types the body as optional, so a body-less delivery is reachable
        $this->sqs->next = [new Message(['MessageId' => 'id-1', 'ReceiptHandle' => self::HANDLE])];

        self::assertNull(SqsQueueFixture::receive());

        // Leaving it in flight would poison every later poll
        $calls = $this->sqs->getCalls('deleteMessage');

        self::assertCount(1, $calls);
        self::assertSame(self::HANDLE, $calls[0]['ReceiptHandle']);
    }

    public function testReceiveAsksForTheReceiveCount(): void
    {
        SqsQueueFixture::receive();

        $input = $this->sqs->getCalls('receiveMessage')[0];

        // SQS reports the count only when it is asked for
        self::assertSame(['ApproximateReceiveCount'], $input['MessageSystemAttributeNames']);
    }

    public function testTheReceiveCountBecomesTheAttemptCount(): void
    {
        $this->seed(new JobFactory()->create(self::NAME), ['ApproximateReceiveCount' => '4']);

        $job = SqsQueueFixture::receive();

        self::assertNotNull($job);
        // SQS owns the count on a processor-owned path, so max_attempts reads it
        self::assertSame(4, $job->getAttempts());
    }

    public function testAnAbsentReceiveCountLeavesTheEnvelopeAlone(): void
    {
        $this->seed(new Job(name: self::NAME, attempts: 2));

        $job = SqsQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(2, $job->getAttempts());
    }

    public function testANonNumericReceiveCountLeavesTheEnvelopeAlone(): void
    {
        $this->seed(new Job(name: self::NAME, attempts: 3), ['ApproximateReceiveCount' => 'not-a-count']);

        $job = SqsQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(3, $job->getAttempts());
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

        self::assertNull(SqsQueueFixture::receive());

        // Nothing is in flight, so a later settle must not touch the queue
        SqsQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->sqs->getCalls('deleteMessage'));
        self::assertSame([], $this->sqs->getCalls('changeMessageVisibility'));
    }

    #[DataProvider('terminalProvider')]
    public function testATerminalOutcomeDeletesTheDelivery(JobResult $result): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $job    = SqsQueueFixture::receive();

        self::assertNotNull($job);

        SqsQueueFixture::settle($job, $result, new InMemoryClient());

        $calls = $this->sqs->getCalls('deleteMessage');

        self::assertCount(1, $calls);
        self::assertSame(self::HANDLE, $calls[0]['ReceiptHandle']);
        self::assertSame([], $this->sqs->getCalls('changeMessageVisibility'));
    }

    public function testARetryHoldsTheDeliveryForItsRamp(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $job    = SqsQueueFixture::receive();

        self::assertNotNull($job);

        SqsQueueFixture::settle($job, JobResult::RETRY, new InMemoryClient());

        $calls = $this->sqs->getCalls('changeMessageVisibility');

        self::assertCount(1, $calls);
        self::assertSame(self::HANDLE, $calls[0]['ReceiptHandle']);
        // The job's own ramp, so a retrying job does not burn every attempt back
        // to back. SQS redelivers once the hold lapses and counts the receive.
        self::assertSame(1, $calls[0]['VisibilityTimeout']);
        self::assertSame([], $this->sqs->getCalls('deleteMessage'));
    }

    public function testARetryWithNoRampComesBackAtOnce(): void
    {
        $this->seed(new Job(name: self::NAME, retryDelayMs: 0));

        $job = SqsQueueFixture::receive();

        self::assertNotNull($job);

        SqsQueueFixture::settle($job, JobResult::RETRY, new InMemoryClient());

        self::assertSame(0, $this->sqs->getCalls('changeMessageVisibility')[0]['VisibilityTimeout']);
    }

    public function testASubSecondRampHoldsForOneSecond(): void
    {
        // Under a second, so rounding down would leave no hold at all
        $this->seed(new Job(name: self::NAME, retryDelayMs: 500));

        $job = SqsQueueFixture::receive();

        self::assertNotNull($job);

        SqsQueueFixture::settle($job, JobResult::RETRY, new InMemoryClient());

        self::assertSame(1, $this->sqs->getCalls('changeMessageVisibility')[0]['VisibilityTimeout']);
    }

    public function testARampOverAWholeSecondRoundsUp(): void
    {
        $this->seed(new Job(name: self::NAME, retryDelayMs: 1500));

        $job = SqsQueueFixture::receive();

        self::assertNotNull($job);

        SqsQueueFixture::settle($job, JobResult::RETRY, new InMemoryClient());

        self::assertSame(2, $this->sqs->getCalls('changeMessageVisibility')[0]['VisibilityTimeout']);
    }

    public function testARampLongerThanSqsAllowsIsClamped(): void
    {
        $this->seed(new Job(name: self::NAME, retryDelayMs: 90_000_000));

        $job = SqsQueueFixture::receive();

        self::assertNotNull($job);

        SqsQueueFixture::settle($job, JobResult::RETRY, new InMemoryClient());

        self::assertSame(
            SqsQueue::MAX_VISIBILITY_TIMEOUT,
            $this->sqs->getCalls('changeMessageVisibility')[0]['VisibilityTimeout']
        );
    }

    public function testReceivingWithoutAConnectionFails(): void
    {
        SqsQueueFixture::reset();

        $this->expectException(QueueServerNotConnectedException::class);

        SqsQueueFixture::receive();
    }

    public function testAnUnreadableEnvelopeIsRetiredRatherThanRedelivered(): void
    {
        // Nothing settles a body the factory cannot read, so SQS would hand the
        // same message back on every visibility timeout
        $this->sqs->next = [
            new Message(['MessageId' => 'id-1', 'ReceiptHandle' => self::HANDLE, 'Body' => '{not json']),
        ];

        self::assertNull(SqsQueueFixture::receive());

        $calls = $this->sqs->getCalls('deleteMessage');

        self::assertCount(1, $calls);
        self::assertSame(self::HANDLE, $calls[0]['ReceiptHandle']);
    }

    public function testAnEnvelopeThatCarriesNoObjectIsRetired(): void
    {
        $this->sqs->next = [
            new Message(['MessageId' => 'id-1', 'ReceiptHandle' => self::HANDLE, 'Body' => '5']),
        ];

        self::assertNull(SqsQueueFixture::receive());
        self::assertCount(1, $this->sqs->getCalls('deleteMessage'));
    }

    public function testAnUnreadableEnvelopeLeavesNothingInFlight(): void
    {
        $this->sqs->next = [
            new Message(['MessageId' => 'id-1', 'ReceiptHandle' => self::HANDLE, 'Body' => '{not json']),
        ];

        SqsQueueFixture::receive();
        SqsQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        // The retire already answered it, so a settle would answer it twice
        self::assertCount(1, $this->sqs->getCalls('deleteMessage'));
    }

    public function testSettlingWithNothingInFlightDoesNothing(): void
    {
        SqsQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->sqs->getCalls('deleteMessage'));
        self::assertSame([], $this->sqs->getCalls('changeMessageVisibility'));
    }

    public function testADeliveryIsSettledOnlyOnce(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $job    = SqsQueueFixture::receive();

        self::assertNotNull($job);

        SqsQueueFixture::settle($job, JobResult::ACK, new InMemoryClient());
        SqsQueueFixture::settle($job, JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->sqs->getCalls('deleteMessage'));
    }

    public function testDisconnectReleasesAnInFlightDelivery(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        SqsQueueFixture::connect($this->application());
        SqsQueueFixture::receive();
        SqsQueueFixture::disconnect();

        $calls = $this->sqs->getCalls('changeMessageVisibility');

        self::assertCount(1, $calls);
        self::assertSame(0, $calls[0]['VisibilityTimeout']);
    }

    public function testDisconnectWithNothingInFlightReleasesNothing(): void
    {
        SqsQueueFixture::connect($this->application());
        SqsQueueFixture::disconnect();

        self::assertSame([], $this->sqs->getCalls('changeMessageVisibility'));
    }

    /**
     * @param array<string, string> $attributes The system attributes SQS reports
     */
    protected function seed(Job $job, array $attributes = []): void
    {
        $this->sqs->next = [
            new Message([
                'MessageId'     => 'id-1',
                'ReceiptHandle' => self::HANDLE,
                'Body'          => new JobFactory()->toJson($job),
                'Attributes'    => $attributes,
            ]),
        ];
    }

    /**
     * Build an application whose container carries the SQS client config.
     */
    protected function application(): ApplicationContract
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn(new QueueSqsClientConfig());

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        return $app;
    }
}
