<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Server\Middleware\ThrowableCaught;

use Override;
use RuntimeException;
use Throwable;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Queue\Server\Middleware\ThrowableCaught\RetryPolicyThrowableCaughtMiddleware;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerNonRetryableJobException;
use Valkyrja\Queue\Server\Throwable\Exception\QueueServerWorkerShutdownException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class RetryPolicyThrowableCaughtMiddlewareTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    protected ThrowableCaughtHandler $handler;

    protected RetryPolicyThrowableCaughtMiddleware $middleware;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->handler    = new ThrowableCaughtHandler(new Container());
        $this->middleware = new RetryPolicyThrowableCaughtMiddleware();
    }

    public function testAnUncaughtThrowableRetriesWhileAttemptsRemain(): void
    {
        $job = new Job(name: self::NAME, attempts: 1, maxAttempts: 3);

        self::assertSame(JobResult::RETRY, $this->map($job, new RuntimeException('boom')));
    }

    public function testAnUncaughtThrowableDeadLettersOnTheLastAttempt(): void
    {
        $job = new Job(name: self::NAME, attempts: 3, maxAttempts: 3);

        self::assertSame(JobResult::DEAD_LETTER, $this->map($job, new RuntimeException('boom')));
    }

    public function testAnUncaughtThrowableDeadLettersPastTheCeiling(): void
    {
        $job = new Job(name: self::NAME, attempts: 4, maxAttempts: 3);

        self::assertSame(JobResult::DEAD_LETTER, $this->map($job, new RuntimeException('boom')));
    }

    public function testANonRetryableThrowableFailsImmediately(): void
    {
        // Attempts remain, but retrying would reproduce the same failure
        $job = new Job(name: self::NAME, attempts: 1, maxAttempts: 5);

        self::assertSame(JobResult::FAIL, $this->map($job, new QueueServerNonRetryableJobException('bad payload')));
    }

    public function testAShutdownRetriesWithoutPenalty(): void
    {
        // Even on the last attempt, a shutdown did not consume the work
        $job = new Job(name: self::NAME, attempts: 5, maxAttempts: 5);

        self::assertSame(JobResult::RETRY, $this->map($job, new QueueServerWorkerShutdownException('draining')));
    }

    public function testTheSeededResultIsReplaced(): void
    {
        $job = new Job(name: self::NAME, attempts: 5, maxAttempts: 5);

        // The seed the kernel passes in is advisory; the policy decides
        self::assertSame(
            JobResult::DEAD_LETTER,
            $this->middleware->throwableCaught($job, JobResult::ACK, new RuntimeException('boom'), $this->handler)
        );
    }

    protected function map(Job $job, Throwable $throwable): JobResult
    {
        return $this->middleware->throwableCaught($job, JobResult::RETRY, $throwable, $this->handler);
    }
}
