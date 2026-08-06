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
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the InMemoryClient.
 */
final class InMemoryClientTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(1768564798.0);
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testDefaults(): void
    {
        $client = new InMemoryClient();

        self::assertSame([], $client->getPushed());
        self::assertSame([], $client->getBuffered());
        self::assertSame([], $client->drain());
    }

    public function testPushBuffersRatherThanRunning(): void
    {
        $client = new InMemoryClient();
        $client->push(new JobFactory()->create(self::NAME));

        // Distinct from the sync client, which runs the job now
        self::assertCount(1, $client->getBuffered());
    }

    public function testPushStampsTheFrameworkOwnedFields(): void
    {
        $client = new InMemoryClient(applicationName: 'worker', version: '26.0.0');
        $client->push(new Job(name: self::NAME, attempts: 3, enqueuedAtMs: 5, modifiedAtMs: 9));

        $pushed = $client->getPushed()[0];

        self::assertSame('worker php/26.0.0', $pushed->getProducer());
        self::assertSame(1, $pushed->getAttempts());
        self::assertSame(1768564798000, $pushed->getEnqueuedAtMs());
        self::assertSame(1768564798000, $pushed->getModifiedAtMs());
    }

    public function testPushLeavesTheProducerAuthoredFieldsAlone(): void
    {
        $client = new InMemoryClient();
        $client->push(new Job(name: self::NAME, maxAttempts: 9, priority: 3, delayMs: 100, retryDelayMs: 250));

        $pushed = $client->getPushed()[0];

        self::assertSame(9, $pushed->getMaxAttempts());
        self::assertSame(3, $pushed->getPriority());
        self::assertSame(100, $pushed->getDelayMs());
        self::assertSame(250, $pushed->getRetryDelayMs());
    }

    public function testPushPreservesAnAlreadyGeneratedId(): void
    {
        $job    = new JobFactory()->create(self::NAME);
        $client = new InMemoryClient();
        $client->push($job);

        self::assertSame($job->getId(), $client->getPushed()[0]->getId());
    }

    public function testRetryDoesNotRestampTheIdOrResetTheAttempts(): void
    {
        $job    = new Job(name: self::NAME, id: 'stable-id', producer: 'other', attempts: 3);
        $client = new InMemoryClient();
        $client->retry($job);

        $pushed = $client->getPushed()[0];

        self::assertSame('stable-id', $pushed->getId());
        self::assertSame(3, $pushed->getAttempts());
        self::assertSame('other', $pushed->getProducer());
    }

    public function testRetryBuffersLikeAPush(): void
    {
        $client = new InMemoryClient();
        $client->retry(new Job(name: self::NAME, attempts: 2));

        self::assertCount(1, $client->getBuffered());
    }

    public function testDrainEmptiesTheBufferButNotTheRecord(): void
    {
        $client = new InMemoryClient();
        $client->push(new JobFactory()->create(self::NAME));

        self::assertCount(1, $client->drain());
        self::assertSame([], $client->getBuffered());
        // getPushed is the lifecycle record, not the buffer
        self::assertCount(1, $client->getPushed());
    }

    public function testGetPushedRecordsEveryHandOver(): void
    {
        $client = new InMemoryClient();
        $client->push(new JobFactory()->create(self::NAME));
        $client->push(new JobFactory()->create('Other'));

        self::assertCount(2, $client->getPushed());
    }

    public function testNowFloorsAtZero(): void
    {
        Microtime::freeze(-1.0);

        $client = new InMemoryClient();
        $client->push(new JobFactory()->create(self::NAME));

        self::assertSame(0, $client->getPushed()[0]->getEnqueuedAtMs());
    }
}
