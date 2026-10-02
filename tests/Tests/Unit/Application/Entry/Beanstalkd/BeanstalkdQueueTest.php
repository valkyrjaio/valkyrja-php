<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry\Beanstalkd;

use Override;
use Pheanstalk\Values\Job as BeanstalkdJob;
use Pheanstalk\Values\JobId;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Application\Entry\Beanstalkd\BeanstalkdQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Data\QueueBeanstalkdClientConfig;
use Valkyrja\Queue\Client\Manager\BeanstalkdClient;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNotConnectedException;
use Valkyrja\Tests\Fixtures\Application\Entry\BeanstalkdQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Client\BeanstalkdFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class BeanstalkdQueueTest extends TestCase
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

        BeanstalkdQueueFixture::inject($this->pheanstalk, self::TUBE, timeout: 3);
    }

    #[Override]
    protected function tearDown(): void
    {
        BeanstalkdQueueFixture::reset();

        parent::tearDown();
    }

    public function testConnectWatchesTheConfiguredTube(): void
    {
        BeanstalkdQueueFixture::connect($this->application());

        self::assertSame([[self::TUBE]], $this->pheanstalk->getCalls('watch'));
    }

    public function testConnectStopsWatchingTheDefaultTube(): void
    {
        // A fresh connection watches `default` already, so without the ignore a
        // reserve could take a job that another producer put on `default`
        BeanstalkdQueueFixture::connect($this->application());

        self::assertSame([[BeanstalkdQueue::DEFAULT_TUBE]], $this->pheanstalk->getCalls('ignore'));
    }

    public function testConnectKeepsTheDefaultTubeWhenItIsTheConfiguredOne(): void
    {
        BeanstalkdQueueFixture::inject($this->pheanstalk, BeanstalkdQueue::DEFAULT_TUBE, timeout: 3);
        BeanstalkdQueueFixture::connect($this->application(BeanstalkdQueue::DEFAULT_TUBE));

        // Ignoring the only watched tube would leave the connection watching none
        self::assertSame([], $this->pheanstalk->getCalls('ignore'));
    }

    public function testReservingWithoutAConnectionFails(): void
    {
        BeanstalkdQueueFixture::reset();

        $this->expectException(QueueServerNotConnectedException::class);

        BeanstalkdQueueFixture::receive();
    }

    public function testAnEmptyTubeYieldsNothing(): void
    {
        self::assertNull(BeanstalkdQueueFixture::receive());
    }

    public function testReserveBlocksForTheConfiguredTimeout(): void
    {
        BeanstalkdQueueFixture::receive();

        self::assertSame([[3]], $this->pheanstalk->getCalls('reserveWithTimeout'));
    }

    public function testAReservedJobIsReadBackAsAJob(): void
    {
        $this->seed(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $job = BeanstalkdQueueFixture::receive();

        self::assertNotNull($job);
        self::assertSame(self::NAME, $job->getName());
        self::assertSame(['user_id' => 42], $job->getPayload()->getAll());
    }

    public function testAnAcknowledgedJobIsDeleted(): void
    {
        $this->reserved();

        BeanstalkdQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([[(string) self::JOB_ID]], $this->pheanstalk->getCalls('delete'));
        self::assertSame([], $this->pheanstalk->getCalls('release'));
        self::assertSame([], $this->pheanstalk->getCalls('bury'));
    }

    public function testARetryReleasesTheJobBackOntoTheTube(): void
    {
        $this->reserved();

        BeanstalkdQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::RETRY, new InMemoryClient());

        $calls = $this->pheanstalk->getCalls('release');

        self::assertCount(1, $calls);
        self::assertSame((string) self::JOB_ID, $calls[0][0]);
        self::assertSame([], $this->pheanstalk->getCalls('delete'));
    }

    public function testAReleaseCarriesThePriorityAndTheHold(): void
    {
        $this->reserved();

        // A release assigns both rather than keeping the job's own, and a
        // sub-second hold rounds up rather than down to no hold at all
        BeanstalkdQueueFixture::settle(
            new Job(name: self::NAME, priority: 24, retryDelayMs: 1500),
            JobResult::RETRY,
            new InMemoryClient()
        );

        $calls = $this->pheanstalk->getCalls('release');

        self::assertCount(1, $calls);
        self::assertSame(BeanstalkdClient::LOWEST_PRIORITY - 24, $calls[0][1]);
        self::assertSame(2, $calls[0][2]);
    }

    public function testAReleaseWithNoHoldIsImmediate(): void
    {
        $this->reserved();

        BeanstalkdQueueFixture::settle(
            new Job(name: self::NAME, retryDelayMs: 0),
            JobResult::RETRY,
            new InMemoryClient()
        );

        self::assertSame(0, $this->pheanstalk->getCalls('release')[0][2]);
    }

    #[DataProvider('deadLetteredProvider')]
    public function testADeadLetteredJobIsBuriedRatherThanDeleted(JobResult $result): void
    {
        // A buried job stays for inspection and can be kicked back on
        $this->reserved();

        BeanstalkdQueueFixture::settle(new JobFactory()->create(self::NAME), $result, new InMemoryClient());

        self::assertSame([[(string) self::JOB_ID, 1024]], $this->pheanstalk->getCalls('bury'));
        self::assertSame([], $this->pheanstalk->getCalls('delete'));
    }

    public function testSettlingWithNothingReservedDoesNothing(): void
    {
        BeanstalkdQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertSame([], $this->pheanstalk->getCalls('delete'));
        self::assertSame([], $this->pheanstalk->getCalls('release'));
        self::assertSame([], $this->pheanstalk->getCalls('bury'));
    }

    public function testAJobIsSettledOnlyOnce(): void
    {
        $this->reserved();

        BeanstalkdQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());
        BeanstalkdQueueFixture::settle(new JobFactory()->create(self::NAME), JobResult::ACK, new InMemoryClient());

        self::assertCount(1, $this->pheanstalk->getCalls('delete'));
    }

    public function testDisconnectReleasesAReservedJob(): void
    {
        $this->reserved();

        // A worker shutting down mid-job must not make the tube wait out the
        // whole time-to-release before another worker can take it
        BeanstalkdQueueFixture::disconnect();

        self::assertCount(1, $this->pheanstalk->getCalls('release'));
        self::assertTrue($this->pheanstalk->disconnected);
    }

    public function testAnUnreadableEnvelopeIsBuriedRatherThanReserved(): void
    {
        // Nothing settles a body the factory cannot read, so the reserve would
        // return the same bytes forever. beanstalkd has no redrive policy.
        $this->pheanstalk->next = new BeanstalkdJob(new JobId(self::JOB_ID), '{not json');

        self::assertNull(BeanstalkdQueueFixture::receive());
        self::assertSame([[(string) self::JOB_ID, 1024]], $this->pheanstalk->getCalls('bury'));
    }

    public function testAnEnvelopeThatCarriesNoObjectIsBuried(): void
    {
        $this->pheanstalk->next = new BeanstalkdJob(new JobId(self::JOB_ID), '5');

        self::assertNull(BeanstalkdQueueFixture::receive());
        self::assertCount(1, $this->pheanstalk->getCalls('bury'));
    }

    public function testAnUnreadableEnvelopeIsNotReleasedOnDisconnect(): void
    {
        $this->pheanstalk->next = new BeanstalkdJob(new JobId(self::JOB_ID), '{not json');

        BeanstalkdQueueFixture::receive();
        BeanstalkdQueueFixture::disconnect();

        // The bury already answered it, so a release would answer it twice
        self::assertSame([], $this->pheanstalk->getCalls('release'));
    }

    public function testDisconnectWithNothingReservedReleasesNothing(): void
    {
        BeanstalkdQueueFixture::disconnect();

        self::assertSame([], $this->pheanstalk->getCalls('release'));
        self::assertTrue($this->pheanstalk->disconnected);
    }

    protected function seed(Job $job): void
    {
        $this->pheanstalk->next = new BeanstalkdJob(new JobId(self::JOB_ID), new JobFactory()->toJson($job));
    }

    protected function reserved(): void
    {
        $this->seed(new JobFactory()->create(self::NAME));

        BeanstalkdQueueFixture::receive();
    }

    /**
     * Build an application whose container carries the beanstalkd client config.
     *
     * @param non-empty-string $tube The tube the worker consumes from
     */
    protected function application(string $tube = self::TUBE): ApplicationContract
    {
        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn(new QueueBeanstalkdClientConfig(beanstalkdTube: $tube));

        $app = self::createStub(ApplicationContract::class);
        $app->method('getContainer')->willReturn($container);

        return $app;
    }
}
