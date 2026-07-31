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
use Valkyrja\Queue\Client\Manager\DatabaseClient;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Queue\Client\DatabaseManagerFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_decode;

/**
 * Test the DatabaseClient.
 */
final class DatabaseClientTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var non-empty-string */
    protected const string QUEUE = 'default';

    /** @var int<0, max> */
    protected const int FROZEN_MS = 1768564798000;

    protected DatabaseManagerFixture $manager;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(1768564798.0);

        $this->manager = new DatabaseManagerFixture();
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testPushWritesTheEnvelopeOntoTheTable(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME, ['user_id' => 42]));

        $inserts = $this->manager->getStatements('INSERT');

        self::assertCount(1, $inserts);
        self::assertStringContainsString('queue_jobs', $inserts[0]->query);
        self::assertTrue($inserts[0]->executed);
        self::assertSame(self::QUEUE, $inserts[0]->bound['queue']);

        /** @var array<string, mixed> $envelope */
        $envelope = json_decode((string) $inserts[0]->bound['envelope'], true);

        self::assertSame(self::NAME, $envelope[EnvelopeField::NAME]);
        self::assertSame(['user_id' => 42], $envelope[EnvelopeField::PAYLOAD]);
        self::assertSame(1, $envelope[EnvelopeField::ATTEMPTS]);
    }

    public function testAnImmediateJobIsAvailableAtOnce(): void
    {
        $this->client()->push(new JobFactory()->create(self::NAME));

        self::assertSame(
            self::FROZEN_MS,
            $this->manager->getStatements('INSERT')[0]->bound['available_at_ms']
        );
    }

    public function testTheProducersDelayPushesTheAvailableInstantOut(): void
    {
        $this->client()->push(new Job(name: self::NAME, delayMs: 5000));

        self::assertSame(
            self::FROZEN_MS + 5000,
            $this->manager->getStatements('INSERT')[0]->bound['available_at_ms']
        );
    }

    public function testThePriorityIsStoredForOrdering(): void
    {
        $this->client()->push(new Job(name: self::NAME, priority: 7));

        self::assertSame(7, $this->manager->getStatements('INSERT')[0]->bound['priority']);
    }

    public function testARetryIsHeldForTheSuppliedDelay(): void
    {
        $this->client()->retry(new Job(name: self::NAME, attempts: 2), 250);

        self::assertSame(
            self::FROZEN_MS + 250,
            $this->manager->getStatements('INSERT')[0]->bound['available_at_ms']
        );
    }

    public function testARetryAppliesTheSuppliedHoldRatherThanDerivingOne(): void
    {
        // The re-queuer owns the ramp, so the client must not read the job's own
        // retry fields — deriving from these would give 750, and the producer's
        // delay is intent recorded at first publish that must not re-fire
        $job = new Job(
            name: self::NAME,
            attempts: 3,
            delayMs: 60_000,
            retryDelayMs: 250,
            retryDelayMultiplyByAttempt: true,
        );

        $this->client()->retry($job, 250);

        self::assertSame(
            self::FROZEN_MS + 250,
            $this->manager->getStatements('INSERT')[0]->bound['available_at_ms']
        );
    }

    public function testARetryWithNoHoldIsAvailableAtOnce(): void
    {
        $this->client()->retry(new Job(name: self::NAME, attempts: 2));

        self::assertSame(
            self::FROZEN_MS,
            $this->manager->getStatements('INSERT')[0]->bound['available_at_ms']
        );
    }

    protected function client(): DatabaseClient
    {
        return new DatabaseClient($this->manager, self::QUEUE);
    }
}
