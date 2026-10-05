<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry\Abstract;

use Override;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Manager\Abstract\InternalClient;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;

use function date_default_timezone_get;
use function date_default_timezone_set;

abstract class InternalQueue extends WorkerQueue
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function bootstrap(QueueConfigContract $config): ApplicationContract
    {
        $basePath = Directory::$basePath;
        $timezone = date_default_timezone_get();

        try {
            return parent::bootstrap($config);
        } finally {
            // Booting sets the process-wide base path and timezone, and both
            // belong to the host application that pushed the job
            static::setProcessState($basePath, $timezone);
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function handle(
        ApplicationContract $app,
        ContainerData $data,
        JobContract $job,
        ClientContract $client,
    ): void {
        $basePath = Directory::$basePath;
        $timezone = date_default_timezone_get();
        $config   = $app->getContainer()->getSingleton(ConfigContract::class);

        static::setProcessState($config->dir, $config->timezone);

        try {
            parent::handle($app, $data, $job, $client);
        } finally {
            static::setProcessState($basePath, $timezone);
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultExceptionHandler(): void
    {
        // The host application owns the exception handler of the process
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function bootstrapThrowableHandler(ApplicationContract $app, ContainerContract $container): void
    {
        // The host application owns the exception handler of the process
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        // The in-process client is the processor here, so it owns settlement
        if ($client instanceof InternalClient) {
            $client->settle($job, $result);
        }
    }

    /**
     * @inheritDoc
     *
     * The outcome is capped before it is settled and recorded. A broker answers
     * a worker shutdown by handing the job to another worker, so the retry
     * policy spends no attempt on it and the handler leaves the ceiling alone.
     * There is no other worker in process, so this entry ends the chain itself
     * rather than dropping the job with a retry as its last word.
     */
    #[Override]
    public static function handleJob(
        ContainerContract $container,
        JobContract $job,
        ClientContract $client,
    ): void {
        $handler = $container->getSingleton(JobHandlerContract::class);

        $result = static::capExhaustedRetry($job, $handler->run($job));

        static::settle($job, $result, $client);

        $handler->resultSettled($job, $result);
    }

    /**
     * Get the config of the queue application that runs each job.
     */
    abstract public static function getConfig(): QueueConfigContract;

    /**
     * Turn a retry the job has no attempt left for into the terminal outcome.
     */
    protected static function capExhaustedRetry(JobContract $job, JobResult $result): JobResult
    {
        return $result === JobResult::RETRY && $job->getAttempts() >= $job->getMaxAttempts()
            ? JobResult::DEAD_LETTER
            : $result;
    }

    /**
     * Set the process-wide state that an application reads.
     *
     * @param non-empty-string $basePath The base path of the application
     * @param string           $timezone The default timezone
     */
    protected static function setProcessState(string $basePath, string $timezone): void
    {
        Directory::$basePath = $basePath;

        /** @psalm-suppress ArgumentTypeCoercion The value comes from a config or from date_default_timezone_get(), and neither is empty */
        date_default_timezone_set($timezone);
    }
}
