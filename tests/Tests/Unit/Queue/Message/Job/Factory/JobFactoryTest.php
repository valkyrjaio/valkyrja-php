<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Job\Factory;

use Override;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class JobFactoryTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var int<0, max> */
    protected const int FROZEN_MS = 1768564798000;

    protected JobFactory $factory;

    /**
     * @return array<non-empty-string, mixed>
     */
    protected static function fullEnvelope(): array
    {
        return [
            EnvelopeField::ID                              => '01JABCDEF0123456789ABCDEFG',
            EnvelopeField::NAME                            => self::NAME,
            EnvelopeField::PRODUCER                        => 'AuthService php/26.2.3',
            EnvelopeField::ATTRIBUTES                      => ['tenant' => ['acme']],
            EnvelopeField::ATTEMPTS                        => 2,
            EnvelopeField::MAX_ATTEMPTS                    => 7,
            EnvelopeField::PRIORITY                        => 3,
            EnvelopeField::DELAY_MS                        => 100,
            EnvelopeField::RETRY_DELAY_MS                  => 250,
            EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT => true,
            EnvelopeField::ENQUEUED_AT_MS                  => self::FROZEN_MS,
            EnvelopeField::ENQUEUED_AT_ISO                 => '2026-01-16T11:59:58.000Z',
            EnvelopeField::MODIFIED_AT_MS                  => self::FROZEN_MS,
            EnvelopeField::MODIFIED_AT_ISO                 => '2026-01-16T11:59:58.000Z',
            EnvelopeField::PAYLOAD                         => ['user_id' => 42],
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(1768564798.0);

        $this->factory = new JobFactory();
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testFromArrayReadsEveryField(): void
    {
        $job = $this->factory->fromArray(self::fullEnvelope());

        self::assertSame('01JABCDEF0123456789ABCDEFG', $job->getId());
        self::assertSame(self::NAME, $job->getName());
        self::assertSame('AuthService php/26.2.3', $job->getProducer());
        self::assertSame(['tenant' => ['acme']], $job->getAttributes()->asArray());
        self::assertSame(2, $job->getAttempts());
        self::assertSame(7, $job->getMaxAttempts());
        self::assertSame(3, $job->getPriority());
        self::assertSame(100, $job->getDelayMs());
        self::assertSame(250, $job->getRetryDelayMs());
        self::assertTrue($job->getRetryDelayMultiplyByAttempt());
        self::assertSame(self::FROZEN_MS, $job->getEnqueuedAtMs());
        self::assertSame(self::FROZEN_MS, $job->getModifiedAtMs());
        self::assertSame(['user_id' => 42], $job->getPayload()->asArray());
    }

    public function testRoundTrip(): void
    {
        $envelope = self::fullEnvelope();

        self::assertSame($envelope, $this->factory->fromArray($envelope)->asArray());
    }

    public function testFromArrayRejectsAMissingName(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray([]);
    }

    public function testFromArrayRejectsAnEmptyName(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray([EnvelopeField::NAME => '']);
    }

    public function testFromArrayRejectsANonStringName(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray([EnvelopeField::NAME => 42]);
    }

    public function testFromArrayDefaultsEveryAbsentField(): void
    {
        // An older producer may not have sent fields this port now knows about
        $job = $this->factory->fromArray([EnvelopeField::NAME => self::NAME]);

        self::assertSame('', $job->getProducer());
        self::assertSame([], $job->getAttributes()->asArray());
        self::assertSame([], $job->getPayload()->asArray());
        self::assertSame(1, $job->getAttempts());
        self::assertSame(Job::DEFAULT_MAX_ATTEMPTS, $job->getMaxAttempts());
        self::assertSame(0, $job->getPriority());
        self::assertSame(0, $job->getDelayMs());
        self::assertSame(Job::DEFAULT_RETRY_DELAY_MS, $job->getRetryDelayMs());
        self::assertFalse($job->getRetryDelayMultiplyByAttempt());
        self::assertSame(self::FROZEN_MS, $job->getEnqueuedAtMs());
        self::assertSame(self::FROZEN_MS, $job->getModifiedAtMs());
        self::assertNotSame('', $job->getId());
    }

    public function testFromArrayIgnoresUnknownFields(): void
    {
        // The contract can gain fields over time without breaking this consumer
        $job = $this->factory->fromArray([EnvelopeField::NAME => self::NAME, 'some_future_field' => 'value']);

        self::assertSame(self::NAME, $job->getName());
    }

    public function testFromArrayDefaultsMistypedFields(): void
    {
        $job = $this->factory->fromArray([
            EnvelopeField::NAME                            => self::NAME,
            EnvelopeField::ID                              => 42,
            EnvelopeField::PRODUCER                        => [],
            EnvelopeField::ATTRIBUTES                      => 'not-an-object',
            EnvelopeField::PAYLOAD                         => 'not-an-object',
            EnvelopeField::ATTEMPTS                        => 'two',
            EnvelopeField::PRIORITY                        => 'three',
            EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT => 'yes',
        ]);

        self::assertNotSame('42', $job->getId());
        self::assertSame('', $job->getProducer());
        self::assertSame([], $job->getAttributes()->asArray());
        self::assertSame([], $job->getPayload()->asArray());
        self::assertSame(1, $job->getAttempts());
        self::assertSame(0, $job->getPriority());
        self::assertFalse($job->getRetryDelayMultiplyByAttempt());
    }

    public function testFromArrayReadsAnExplicitFalseFlag(): void
    {
        // A wire `false` is a boolean the guard accepts, so it reaches the
        // second half of the test. Every other case fails the first half
        $job = $this->factory->fromArray([
            EnvelopeField::NAME                            => self::NAME,
            EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT => false,
        ]);

        self::assertFalse($job->getRetryDelayMultiplyByAttempt());
    }

    public function testFromArrayDefaultsAnEmptyId(): void
    {
        $job = $this->factory->fromArray([EnvelopeField::NAME => self::NAME, EnvelopeField::ID => '']);

        self::assertNotSame('', $job->getId());
    }

    public function testFromArrayDefaultsOutOfRangeNumbers(): void
    {
        $job = $this->factory->fromArray([
            EnvelopeField::NAME           => self::NAME,
            EnvelopeField::ATTEMPTS       => 0,
            EnvelopeField::MAX_ATTEMPTS   => -1,
            EnvelopeField::DELAY_MS       => -1,
            EnvelopeField::RETRY_DELAY_MS => -1,
            EnvelopeField::ENQUEUED_AT_MS => -1,
            EnvelopeField::MODIFIED_AT_MS => -1,
        ]);

        self::assertSame(1, $job->getAttempts());
        self::assertSame(Job::DEFAULT_MAX_ATTEMPTS, $job->getMaxAttempts());
        self::assertSame(0, $job->getDelayMs());
        self::assertSame(Job::DEFAULT_RETRY_DELAY_MS, $job->getRetryDelayMs());
        self::assertSame(self::FROZEN_MS, $job->getEnqueuedAtMs());
        self::assertSame(self::FROZEN_MS, $job->getModifiedAtMs());
    }

    public function testFromArrayAcceptsZeroDelays(): void
    {
        $job = $this->factory->fromArray([
            EnvelopeField::NAME           => self::NAME,
            EnvelopeField::DELAY_MS       => 0,
            EnvelopeField::RETRY_DELAY_MS => 0,
            EnvelopeField::ENQUEUED_AT_MS => 0,
        ]);

        self::assertSame(0, $job->getDelayMs());
        self::assertSame(0, $job->getRetryDelayMs());
        self::assertSame(0, $job->getEnqueuedAtMs());
    }

    public function testToJson(): void
    {
        $job = new Job(
            name: self::NAME,
            id: '01JABCDEF0123456789ABCDEFG',
            enqueuedAtMs: self::FROZEN_MS,
        );

        self::assertJson($this->factory->toJson($job));
    }

    public function testJsonRoundTrip(): void
    {
        $job = $this->factory->fromArray(self::fullEnvelope());

        self::assertSame(
            $job->asArray(),
            $this->factory->fromJson($this->factory->toJson($job))->asArray()
        );
    }
}
