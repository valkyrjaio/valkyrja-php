<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Job;

use Override;
use Valkyrja\Queue\Message\Attributes\Attributes;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Message\Payload\Payload;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Type\Vlid\Factory\VlidV1Factory;

final class JobTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var int<0, max> */
    protected const int FROZEN_MS = 1768564798000;

    /** @var float */
    protected const float FROZEN_MICROTIME = 1768564798.0;

    /** @var int<0, max> */
    protected const int SUB_SECOND_MS = 1768564798123;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(self::FROZEN_MICROTIME);
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testDefaults(): void
    {
        $job = new Job(name: self::NAME);

        self::assertSame(self::NAME, $job->getName());
        self::assertSame('', $job->getProducer());
        self::assertSame(1, $job->getAttempts());
        self::assertSame(Job::DEFAULT_MAX_ATTEMPTS, $job->getMaxAttempts());
        self::assertSame(0, $job->getPriority());
        self::assertSame(0, $job->getDelayMs());
        self::assertSame(Job::DEFAULT_RETRY_DELAY_MS, $job->getRetryDelayMs());
        self::assertFalse($job->getRetryDelayMultiplyByAttempt());
        self::assertSame([], $job->getPayload()->asArray());
        self::assertSame([], $job->getAttributes()->asArray());
        self::assertMatchesRegularExpression('/^' . VlidV1Factory::REGEX . '$/', $job->getId());
    }

    public function testTimestampsDefaultToNow(): void
    {
        $job = new Job(name: self::NAME);

        self::assertSame(self::FROZEN_MS, $job->getEnqueuedAtMs());
        self::assertSame(self::FROZEN_MS, $job->getModifiedAtMs());
    }

    public function testModifiedAtDefaultsToTheGivenEnqueuedAt(): void
    {
        $job = new Job(name: self::NAME, enqueuedAtMs: 500);

        self::assertSame(500, $job->getEnqueuedAtMs());
        self::assertSame(500, $job->getModifiedAtMs());
    }

    public function testIsoIsRenderedFromMilliseconds(): void
    {
        $job = new Job(name: self::NAME, enqueuedAtMs: self::SUB_SECOND_MS, modifiedAtMs: 0);

        self::assertSame('2026-01-16T11:59:58.123Z', $job->getEnqueuedAtIso());
        self::assertSame('1970-01-01T00:00:00.000Z', $job->getModifiedAtIso());
    }

    public function testConstructor(): void
    {
        $payload    = new Payload(['user_id' => 42]);
        $attributes = new Attributes(['tenant' => ['acme']]);

        $job = new Job(
            name: self::NAME,
            payload: $payload,
            attributes: $attributes,
            id: 'given-id',
            producer: 'AuthService php/26.2.3',
            attempts: 2,
            maxAttempts: 7,
            priority: 3,
            delayMs: 100,
            retryDelayMs: 250,
            retryDelayMultiplyByAttempt: true,
            enqueuedAtMs: 1000,
            modifiedAtMs: 2000,
        );

        self::assertSame($payload, $job->getPayload());
        self::assertSame($attributes, $job->getAttributes());
        self::assertSame('given-id', $job->getId());
        self::assertSame('AuthService php/26.2.3', $job->getProducer());
        self::assertSame(2, $job->getAttempts());
        self::assertSame(7, $job->getMaxAttempts());
        self::assertSame(3, $job->getPriority());
        self::assertSame(100, $job->getDelayMs());
        self::assertSame(250, $job->getRetryDelayMs());
        self::assertTrue($job->getRetryDelayMultiplyByAttempt());
        self::assertSame(1000, $job->getEnqueuedAtMs());
        self::assertSame(2000, $job->getModifiedAtMs());
    }

    public function testCreateFromArray(): void
    {
        $job = new JobFactory()->create(self::NAME, ['user_id' => 42]);

        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->asArray());
    }

    public function testCreateFromPayload(): void
    {
        $payload = new Payload(['user_id' => 42]);

        self::assertSame($payload, new JobFactory()->create(self::NAME, $payload)->getPayload());
    }

    public function testCreateDefaultsToAnEmptyPayload(): void
    {
        self::assertSame([], new JobFactory()->create(self::NAME)->getPayload()->asArray());
    }

    public function testTheRampIsOffByDefault(): void
    {
        self::assertFalse(new Job(name: self::NAME)->getRetryDelayMultiplyByAttempt());
    }

    public function testRetryDelayForAttemptIsFixedByDefault(): void
    {
        $job = new Job(name: self::NAME, retryDelayMs: 250);

        // The same hold on every retry, with no ramp and no jitter
        self::assertSame(250, $job->getRetryDelayForAttemptMs());
        self::assertSame(250, $job->withAttempts(2)->getRetryDelayForAttemptMs());
        self::assertSame(250, $job->withAttempts(4)->getRetryDelayForAttemptMs());
    }

    public function testTheFirstRetryWaitsExactlyOneDelay(): void
    {
        // The case an earlier revision of the contract got wrong: the hold is
        // keyed to the dispatched attempt, so a job failing its first delivery
        // waits one delay, not two
        $job = new Job(name: self::NAME, attempts: 1, retryDelayMs: 1000, retryDelayMultiplyByAttempt: true);

        self::assertSame(1000, $job->getRetryDelayForAttemptMs());
    }

    public function testRetryDelayForAttemptRampsLinearly(): void
    {
        $job = new Job(name: self::NAME, retryDelayMs: 1000, retryDelayMultiplyByAttempt: true);

        // hold = retryDelayMs * attempts, read from the dispatched job
        self::assertSame(1000, $job->withAttempts(1)->getRetryDelayForAttemptMs());
        self::assertSame(2000, $job->withAttempts(2)->getRetryDelayForAttemptMs());
        self::assertSame(3000, $job->withAttempts(3)->getRetryDelayForAttemptMs());
    }

    public function testWithName(): void
    {
        $job = new Job(name: self::NAME);
        $new = $job->withName('Other');

        self::assertNotSame($job, $new);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame('Other', $new->getName());
    }

    public function testWithPayload(): void
    {
        $payload = new Payload(['a' => 1]);
        $new     = new Job(name: self::NAME)->withPayload($payload);

        self::assertSame($payload, $new->getPayload());
    }

    public function testWithAttributes(): void
    {
        $attributes = new Attributes(['tenant' => ['acme']]);
        $new        = new Job(name: self::NAME)->withAttributes($attributes);

        self::assertSame($attributes, $new->getAttributes());
    }

    public function testWithProducer(): void
    {
        self::assertSame('app php/1.0', new Job(name: self::NAME)->withProducer('app php/1.0')->getProducer());
    }

    public function testWithId(): void
    {
        self::assertSame('other', new Job(name: self::NAME)->withId('other')->getId());
    }

    public function testWithAttempts(): void
    {
        self::assertSame(3, new Job(name: self::NAME)->withAttempts(3)->getAttempts());
    }

    public function testWithMaxAttempts(): void
    {
        self::assertSame(9, new Job(name: self::NAME)->withMaxAttempts(9)->getMaxAttempts());
    }

    public function testWithPriority(): void
    {
        self::assertSame(-1, new Job(name: self::NAME)->withPriority(-1)->getPriority());
    }

    public function testWithDelayMs(): void
    {
        self::assertSame(50, new Job(name: self::NAME)->withDelayMs(50)->getDelayMs());
    }

    public function testWithRetryDelayMs(): void
    {
        self::assertSame(50, new Job(name: self::NAME)->withRetryDelayMs(50)->getRetryDelayMs());
    }

    public function testWithRetryDelayMultiplyByAttempt(): void
    {
        self::assertTrue(new Job(name: self::NAME)->withRetryDelayMultiplyByAttempt(true)->getRetryDelayMultiplyByAttempt());
    }

    public function testWithEnqueuedAtMs(): void
    {
        self::assertSame(5, new Job(name: self::NAME)->withEnqueuedAtMs(5)->getEnqueuedAtMs());
    }

    public function testWithModifiedAtMs(): void
    {
        self::assertSame(5, new Job(name: self::NAME)->withModifiedAtMs(5)->getModifiedAtMs());
    }

    public function testConstructorRejectsEmptyName(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: '');
    }

    public function testWithNameRejectsEmptyName(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME)->withName('');
    }

    public function testWithIdRejectsEmptyId(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME)->withId('');
    }

    public function testConstructorRejectsNonPositiveAttempts(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME, attempts: 0);
    }

    public function testConstructorRejectsNonPositiveMaxAttempts(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME, maxAttempts: 0);
    }

    public function testWithAttemptsRejectsNonPositiveAttempts(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME)->withAttempts(0);
    }

    public function testWithMaxAttemptsRejectsNonPositiveMaxAttempts(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME)->withMaxAttempts(0);
    }

    public function testConstructorRejectsNegativeDelay(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME, delayMs: -1);
    }

    public function testConstructorRejectsNegativeRetryDelay(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME, retryDelayMs: -1);
    }

    public function testWithDelayMsRejectsNegativeDelay(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME)->withDelayMs(-1);
    }

    public function testWithRetryDelayMsRejectsNegativeDelay(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME)->withRetryDelayMs(-1);
    }

    public function testWithEnqueuedAtMsRejectsNegativeTimestamp(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME)->withEnqueuedAtMs(-1);
    }

    public function testWithModifiedAtMsRejectsNegativeTimestamp(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        /* @phpstan-ignore-next-line */
        new Job(name: self::NAME)->withModifiedAtMs(-1);
    }

    public function testNowFloorsAtZero(): void
    {
        Microtime::freeze(-1.0);

        self::assertSame(0, new Job(name: self::NAME)->getEnqueuedAtMs());
    }

    public function testToArrayWritesEveryFieldIncludingDefaults(): void
    {
        $job = new Job(
            name: self::NAME,
            payload: new Payload(['user_id' => 42]),
            attributes: new Attributes(['tenant' => ['acme']]),
            id: '01JABCDEF0123456789ABCDEFG',
            producer: 'AuthService php/26.2.3',
            enqueuedAtMs: self::FROZEN_MS,
            modifiedAtMs: self::FROZEN_MS,
        );

        self::assertSame(
            [
                EnvelopeField::ID                              => '01JABCDEF0123456789ABCDEFG',
                EnvelopeField::NAME                            => self::NAME,
                EnvelopeField::PRODUCER                        => 'AuthService php/26.2.3',
                EnvelopeField::ATTRIBUTES                      => ['tenant' => ['acme']],
                EnvelopeField::ATTEMPTS                        => 1,
                EnvelopeField::MAX_ATTEMPTS                    => 5,
                EnvelopeField::PRIORITY                        => 0,
                EnvelopeField::DELAY_MS                        => 0,
                EnvelopeField::RETRY_DELAY_MS                  => 1000,
                EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT => false,
                EnvelopeField::ENQUEUED_AT_MS                  => self::FROZEN_MS,
                EnvelopeField::ENQUEUED_AT_ISO                 => '2026-01-16T11:59:58.000Z',
                EnvelopeField::MODIFIED_AT_MS                  => self::FROZEN_MS,
                EnvelopeField::MODIFIED_AT_ISO                 => '2026-01-16T11:59:58.000Z',
                EnvelopeField::PAYLOAD                         => ['user_id' => 42],
            ],
            $job->asArray()
        );
    }

    public function testToArrayWritesEmptyAttributesAndPayloadAsObjects(): void
    {
        $envelope = new Job(name: self::NAME)->asArray();

        // Empty is never absent — a consumer must not have to tell one from the other
        self::assertSame([], $envelope[EnvelopeField::ATTRIBUTES]);
        self::assertSame([], $envelope[EnvelopeField::PAYLOAD]);
    }
}
