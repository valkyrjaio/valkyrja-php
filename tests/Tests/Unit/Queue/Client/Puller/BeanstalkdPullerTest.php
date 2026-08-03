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
use Pheanstalk\Values\Job as BeanstalkdJob;
use Pheanstalk\Values\JobId;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Client\Puller\BeanstalkdPuller;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Tests\Fixtures\Queue\Client\BeanstalkdFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the BeanstalkdPuller.
 */
final class BeanstalkdPullerTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string TUBE = 'valkyrja';

    protected const int JOB_ID = 7;

    protected BeanstalkdFixture $pheanstalk;

    /**
     * @return array<string, array{JobResult}>
     */
    public static function deadLetteredProvider(): array
    {
        return [
            'fail'        => [JobResult::FAIL],
            'dead letter' => [JobResult::DEAD_LETTER],
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->pheanstalk = new BeanstalkdFixture();
    }

    public function testConnectWatchesTheConfiguredTube(): void
    {
        $this->puller()->connect();

        self::assertSame([[self::TUBE]], $this->pheanstalk->getCalls('watch'));
    }

    public function testConnectStopsWatchingTheDefaultTube(): void
    {
        // A fresh connection watches `default` already, so without the ignore a
        // reserve could take a job that another producer put on `default`
        $this->puller()->connect();

        self::assertSame([[BeanstalkdPuller::DEFAULT_TUBE]], $this->pheanstalk->getCalls('ignore'));
    }

    public function testConnectKeepsTheDefaultTubeWhenItIsTheConfiguredOne(): void
    {
        new BeanstalkdPuller($this->pheanstalk, BeanstalkdPuller::DEFAULT_TUBE, timeout: 3)->connect();

        // Ignoring the only watched tube would leave the connection watching none
        self::assertSame([], $this->pheanstalk->getCalls('ignore'));
    }

    public function testAnEmptyTubeYieldsNothing(): void
    {
        self::assertNull($this->puller()->receive());
    }

    public function testReserveBlocksForTheConfiguredTimeout(): void
    {
        $this->puller()->receive();

        self::assertSame([[3]], $this->pheanstalk->getCalls('reserveWithTimeout'));
    }

    public function testAReservedJobIsReadBackAsAJob(): void
    {
        $this->seed(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $job = $this->puller()->receive();

        self::assertNotNull($job);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->getAll());
    }

    public function testAnAcknowledgedJobIsDeleted(): void
    {
        $puller = $this->reserved();

        $puller->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([[(string) self::JOB_ID]], $this->pheanstalk->getCalls('delete'));
        self::assertSame([], $this->pheanstalk->getCalls('release'));
        self::assertSame([], $this->pheanstalk->getCalls('bury'));
    }

    public function testARetryReleasesTheJobBackOntoTheTube(): void
    {
        $puller = $this->reserved();

        $puller->settle(new JobFactory()->create(self::NAME), JobResult::RETRY, new InMemoryClient());

        $calls = $this->pheanstalk->getCalls('release');

        self::assertCount(1, $calls);
        self::assertSame((string) self::JOB_ID, $calls[0][0]);
        self::assertSame([], $this->pheanstalk->getCalls('delete'));
    }

    #[DataProvider('deadLetteredProvider')]
    public function testADeadLetteredJobIsBuriedRatherThanDeleted(JobResult $result): void
    {
        // A buried job stays for inspection and can be kicked back on
        $puller = $this->reserved();

        $puller->settle(new JobFactory()->create(self::NAME), $result, new InMemoryClient());

        self::assertSame([[(string) self::JOB_ID, 1024]], $this->pheanstalk->getCalls('bury'));
        self::assertSame([], $this->pheanstalk->getCalls('delete'));
    }

    public function testSettlingWithNothingReservedDoesNothing(): void
    {
        $this->puller()->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->pheanstalk->getCalls('delete'));
        self::assertSame([], $this->pheanstalk->getCalls('release'));
        self::assertSame([], $this->pheanstalk->getCalls('bury'));
    }

    public function testAJobIsSettledOnlyOnce(): void
    {
        $puller = $this->reserved();

        $puller->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());
        $puller->settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->pheanstalk->getCalls('delete'));
    }

    public function testDisconnectReleasesAReservedJob(): void
    {
        $puller = $this->reserved();

        // A worker shutting down mid-job must not make the tube wait out the
        // whole time-to-release before another worker can take it
        $puller->disconnect();

        self::assertCount(1, $this->pheanstalk->getCalls('release'));
        self::assertTrue($this->pheanstalk->disconnected);
    }

    public function testDisconnectWithNothingReservedReleasesNothing(): void
    {
        $this->puller()->disconnect();

        self::assertSame([], $this->pheanstalk->getCalls('release'));
        self::assertTrue($this->pheanstalk->disconnected);
    }

    protected function seed(Job $job): void
    {
        $this->pheanstalk->next = new BeanstalkdJob(new JobId(self::JOB_ID), new JobFactory()->toJson($job));
    }

    protected function reserved(): BeanstalkdPuller
    {
        $this->seed(new JobFactory()->create(self::NAME));

        $puller = $this->puller();
        $puller->receive();

        return $puller;
    }

    protected function puller(): BeanstalkdPuller
    {
        return new BeanstalkdPuller($this->pheanstalk, self::TUBE, timeout: 3);
    }
}
