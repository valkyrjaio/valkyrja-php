<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Handler;

use Override;
use Throwable;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Handler\Contract\JobReceivedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\SettlingResultHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Queue\Middleware\Handler\JobReceivedHandler;
use Valkyrja\Queue\Middleware\Handler\ResultSettledHandler;
use Valkyrja\Queue\Middleware\Handler\SettlingResultHandler;
use Valkyrja\Queue\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Queue\Routing\Dispatcher\Router;
use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;

class JobHandler implements JobHandlerContract
{
    public function __construct(
        protected ContainerContract $container = new Container(),
        protected RouterContract $router = new Router(),
        protected JobReceivedHandlerContract $jobReceivedHandler = new JobReceivedHandler(),
        protected ThrowableCaughtHandlerContract $throwableCaughtHandler = new ThrowableCaughtHandler(),
        protected SettlingResultHandlerContract $settlingResultHandler = new SettlingResultHandler(),
        protected ResultSettledHandlerContract $resultSettledHandler = new ResultSettledHandler(),
        protected bool $debug = false,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws Throwable
     */
    #[Override]
    public function handle(JobContract $job): JobResult
    {
        try {
            $result = $this->dispatchRouter($job);
        } catch (Throwable $throwable) {
            $result = $this->getResultFromThrowable($throwable);
            $result = $this->throwableCaughtHandler->throwableCaught($job, $result, $throwable);
        }

        return $this->capRetries($job, $result);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settlingResult(JobContract $job, JobResult $result): JobResult
    {
        return $this->settlingResultHandler->settlingResult($job, $result);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function resultSettled(JobContract $job, JobResult $result): void
    {
        $this->resultSettledHandler->resultSettled($job, $result);
    }

    /**
     * @inheritDoc
     *
     * @throws Throwable
     */
    #[Override]
    public function run(JobContract $job): JobResult
    {
        return $this->settlingResult($job, $this->handle($job));
    }

    /**
     * Dispatch the job via the router.
     */
    protected function dispatchRouter(JobContract $job): JobResult
    {
        // Set the job in the container
        $this->container->setSingleton(JobContract::class, $job);

        // Dispatch the job received middleware
        $jobAfterMiddleware = $this->jobReceivedHandler->jobReceived($job);

        // If the return value after middleware is a result return it
        if ($jobAfterMiddleware instanceof JobResult) {
            return $jobAfterMiddleware;
        }

        // Set the returned job in the container
        $this->container->setSingleton(JobContract::class, $jobAfterMiddleware);

        return $this->router->dispatch($jobAfterMiddleware);
    }

    /**
     * Convert an exhausted retry into a dead-letter.
     *
     * A retry is the one outcome a handler may return that the framework
     * overrides: once the attempts reach the ceiling there is nowhere left to
     * retry into. Applying it here rather than at settlement means the
     * SettlingResult stage sees the outcome the adapter will actually act on.
     */
    protected function capRetries(JobContract $job, JobResult $result): JobResult
    {
        if ($result === JobResult::RETRY && $job->getAttempts() >= $job->getMaxAttempts()) {
            return JobResult::DEAD_LETTER;
        }

        return $result;
    }

    /**
     * Seed the outcome the ThrowableCaught chain starts from.
     *
     * A throw means the job did not finish, so the neutral seed is a retry;
     * the chain's default policy middleware then applies the attempt ceiling
     * and the non-retryable rules on top of it.
     *
     * @throws Throwable
     */
    protected function getResultFromThrowable(Throwable $throwable): JobResult
    {
        if ($this->debug) {
            throw $throwable;
        }

        return JobResult::RETRY;
    }
}
