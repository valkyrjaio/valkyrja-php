<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Server\Handler;

use Override;
use RuntimeException;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Middleware\Handler\JobReceivedHandler;
use Valkyrja\Queue\Middleware\Handler\ResultSettledHandler;
use Valkyrja\Queue\Middleware\Handler\SettlingResultHandler;
use Valkyrja\Queue\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Queue\Routing\Collection\RouteCollection;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\Route;
use Valkyrja\Queue\Routing\Dispatcher\Router;
use Valkyrja\Queue\Server\Handler\JobHandler;
use Valkyrja\Tests\Fixtures\Queue\Middleware\JobReceivedMiddlewareChangedFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\JobReceivedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareChangedFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class JobHandlerTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    protected Container $container;

    protected RouteCollection $collection;

    protected JobReceivedHandler $jobReceivedHandler;

    protected ThrowableCaughtHandler $throwableCaughtHandler;

    protected SettlingResultHandler $settlingResultHandler;

    protected ResultSettledHandler $resultSettledHandler;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container              = new Container();
        $this->collection             = new RouteCollection();
        $this->jobReceivedHandler     = new JobReceivedHandler($this->container);
        $this->throwableCaughtHandler = new ThrowableCaughtHandler($this->container);
        $this->settlingResultHandler  = new SettlingResultHandler($this->container);
        $this->resultSettledHandler   = new ResultSettledHandler($this->container);
    }

    public function testHandleDispatchesThroughTheRouter(): void
    {
        $this->collection->add($this->route(static fn (): JobResult => JobResult::ACK));

        self::assertSame(JobResult::ACK, $this->handler()->handle(new Job(name: self::NAME)));
    }

    public function testHandlePublishesTheJobInTheContainer(): void
    {
        $this->collection->add($this->route(static fn (): JobResult => JobResult::ACK));

        $job = new Job(name: self::NAME);

        $this->handler()->handle($job);

        self::assertSame($job, $this->container->getSingleton(JobContract::class));
    }

    public function testHandleRunsTheJobReceivedStage(): void
    {
        JobReceivedMiddlewareFixture::resetCounter();
        $this->jobReceivedHandler->add(JobReceivedMiddlewareFixture::class);
        $this->collection->add($this->route(static fn (): JobResult => JobResult::ACK));

        self::assertSame(JobResult::ACK, $this->handler()->handle(new Job(name: self::NAME)));
        self::assertSame(1, JobReceivedMiddlewareFixture::getCounter());
    }

    public function testJobReceivedMiddlewareCanShortCircuit(): void
    {
        JobReceivedMiddlewareChangedFixture::resetCounter();
        $this->jobReceivedHandler->add(JobReceivedMiddlewareChangedFixture::class);

        // The middleware settles the job without the router ever running
        self::assertSame(JobResult::FAIL, $this->handler()->handle(new Job(name: 'Unrouted')));
        self::assertSame(1, JobReceivedMiddlewareChangedFixture::getCounter());
    }

    public function testHandleSeedsARetryFromAThrowable(): void
    {
        $this->collection->add($this->route(static fn (): JobResult => throw new RuntimeException('boom')));

        // With no policy middleware registered the neutral seed survives
        self::assertSame(JobResult::RETRY, $this->handler()->handle(new Job(name: self::NAME)));
    }

    public function testThrowableCaughtMiddlewareCanChangeTheOutcome(): void
    {
        ThrowableCaughtMiddlewareChangedFixture::resetCounter();
        $this->throwableCaughtHandler->add(ThrowableCaughtMiddlewareChangedFixture::class);
        $this->collection->add($this->route(static fn (): JobResult => throw new RuntimeException('boom')));

        self::assertSame(JobResult::DEAD_LETTER, $this->handler()->handle(new Job(name: self::NAME)));
        self::assertSame(1, ThrowableCaughtMiddlewareChangedFixture::getCounter());
    }

    public function testAHandlerReturnedRetryIsCappedAtTheCeiling(): void
    {
        $this->collection->add($this->route(static fn (): JobResult => JobResult::RETRY));

        // A retry is the one outcome the framework overrides: on the last
        // attempt there is nowhere left to retry into
        self::assertSame(
            JobResult::DEAD_LETTER,
            $this->handler()->handle(new Job(name: self::NAME, attempts: 3, maxAttempts: 3))
        );
    }

    public function testAHandlerReturnedRetrySurvivesWhileAttemptsRemain(): void
    {
        $this->collection->add($this->route(static fn (): JobResult => JobResult::RETRY));

        self::assertSame(
            JobResult::RETRY,
            $this->handler()->handle(new Job(name: self::NAME, attempts: 2, maxAttempts: 3))
        );
    }

    public function testATerminalOutcomeIsNeverCapped(): void
    {
        $this->collection->add($this->route(static fn (): JobResult => JobResult::ACK));

        self::assertSame(
            JobResult::ACK,
            $this->handler()->handle(new Job(name: self::NAME, attempts: 3, maxAttempts: 3))
        );
    }

    public function testHandleRethrowsInDebugMode(): void
    {
        $this->collection->add($this->route(static fn (): JobResult => throw new RuntimeException('boom')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->handler(debug: true)->handle(new Job(name: self::NAME));
    }

    public function testSettlingResultRunsItsStage(): void
    {
        SettlingResultMiddlewareFixture::resetCounter();
        $this->settlingResultHandler->add(SettlingResultMiddlewareFixture::class);

        self::assertSame(
            JobResult::ACK,
            $this->handler()->settlingResult(new Job(name: self::NAME), JobResult::ACK)
        );
        self::assertSame(1, SettlingResultMiddlewareFixture::getCounter());
    }

    public function testResultSettledRunsItsStage(): void
    {
        ResultSettledMiddlewareFixture::resetCounter();
        $this->resultSettledHandler->add(ResultSettledMiddlewareFixture::class);

        $this->handler()->resultSettled(new Job(name: self::NAME), JobResult::ACK);

        self::assertSame(1, ResultSettledMiddlewareFixture::getCounter());
    }

    public function testRunBundlesHandleAndSettlingResult(): void
    {
        SettlingResultMiddlewareFixture::resetCounter();
        $this->settlingResultHandler->add(SettlingResultMiddlewareFixture::class);
        $this->collection->add($this->route(static fn (): JobResult => JobResult::ACK));

        self::assertSame(JobResult::ACK, $this->handler()->run(new Job(name: self::NAME)));
        self::assertSame(1, SettlingResultMiddlewareFixture::getCounter());
    }

    public function testRunLeavesResultSettledForTheAdapter(): void
    {
        ResultSettledMiddlewareFixture::resetCounter();
        $this->resultSettledHandler->add(ResultSettledMiddlewareFixture::class);
        $this->collection->add($this->route(static fn (): JobResult => JobResult::ACK));

        $this->handler()->run(new Job(name: self::NAME));

        // The adapter settles between run() and resultSettled(), so run() must not fire it
        self::assertSame(0, ResultSettledMiddlewareFixture::getCounter());
    }

    /**
     * @param callable(ContainerContract, RouteContract):JobResult $handler
     */
    protected function route(callable $handler): Route
    {
        return new Route(
            name: self::NAME,
            description: 'Send the welcome email',
            handler: $handler,
        );
    }

    protected function handler(bool $debug = false): JobHandler
    {
        return new JobHandler(
            container: $this->container,
            router: new Router(container: $this->container, collection: $this->collection),
            jobReceivedHandler: $this->jobReceivedHandler,
            throwableCaughtHandler: $this->throwableCaughtHandler,
            settlingResultHandler: $this->settlingResultHandler,
            resultSettledHandler: $this->resultSettledHandler,
            debug: $debug,
        );
    }
}
