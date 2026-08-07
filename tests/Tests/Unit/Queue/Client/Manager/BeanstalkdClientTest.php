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
use Valkyrja\Queue\Client\Manager\BeanstalkdClient;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Client\BeanstalkdFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_decode;

final class BeanstalkdClientTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string TUBE = 'valkyrja';

    protected BeanstalkdFixture $pheanstalk;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->pheanstalk = new BeanstalkdFixture();
    }

    public function testPushPutsTheEnvelopeOnTheTube(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        self::assertSame([[self::TUBE]], $this->pheanstalk->getCalls('useTube'));

        $calls = $this->pheanstalk->getCalls('put');

        self::assertCount(1, $calls);

        /** @var array<string, mixed> $envelope */
        $envelope = json_decode($calls[0][0], true);

        self::assertSame(self::NAME, $envelope[EnvelopeField::NAME]);
        self::assertSame(['user_id' => 42], $envelope[EnvelopeField::PAYLOAD]);
        self::assertSame(1, $envelope[EnvelopeField::ATTEMPTS]);
    }

    public function testPushCarriesTheConfiguredTimeToRelease(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME));

        self::assertSame(90, $this->pheanstalk->getCalls('put')[0][3]);
    }

    public function testAnImmediateJobCarriesNoDelay(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME));

        self::assertSame(0, $this->pheanstalk->getCalls('put')[0][2]);
    }

    public function testTheProducersDelayIsConvertedToWholeSeconds(): void
    {
        $this->client()->push(new Job(name: self::NAME, delayMs: 5000));

        self::assertSame(5, $this->pheanstalk->getCalls('put')[0][2]);
    }

    public function testASubSecondDelayRoundsDownToZero(): void
    {
        // beanstalkd has no finer grain than a second
        $this->client()->push(new Job(name: self::NAME, delayMs: 999));

        self::assertSame(0, $this->pheanstalk->getCalls('put')[0][2]);
    }

    public function testADefaultPriorityMapsToTheLeastUrgentEnd(): void
    {
        // beanstalkd counts the other way round, so an envelope priority of 0
        // is the least urgent number it takes
        $this->client()->push(new JobFactory()->create(self::NAME));

        self::assertSame(
            BeanstalkdClient::LOWEST_PRIORITY,
            $this->pheanstalk->getCalls('put')[0][1]
        );
    }

    public function testAHigherEnvelopePriorityBecomesALowerBeanstalkdNumber(): void
    {
        $this->client()->push(new Job(name: self::NAME, priority: 24));

        self::assertSame(
            BeanstalkdClient::LOWEST_PRIORITY - 24,
            $this->pheanstalk->getCalls('put')[0][1]
        );
    }

    public function testAPriorityBeyondTheProcessorsRangeIsClamped(): void
    {
        $this->client()->push(new Job(name: self::NAME, priority: 99_999));

        self::assertSame(0, $this->pheanstalk->getCalls('put')[0][1]);
    }

    public function testANegativePriorityIsClampedToTheLeastUrgentEnd(): void
    {
        $this->client()->push(new Job(name: self::NAME, priority: -5));

        self::assertSame(
            BeanstalkdClient::LOWEST_PRIORITY,
            $this->pheanstalk->getCalls('put')[0][1]
        );
    }

    public function testARetryPutsNothingBecauseTheProcessorOwnsRedelivery(): void
    {
        // The original job is still reserved; putting it would duplicate it
        $this->client()->retry(new Job(name: self::NAME, attempts: 2), 5000);

        self::assertSame([], $this->pheanstalk->getCalls('put'));
    }

    protected function client(): BeanstalkdClient
    {
        return new BeanstalkdClient($this->pheanstalk, self::TUBE, timeToRelease: 90);
    }
}
