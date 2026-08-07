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

use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Kernel\ChildApplication;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\ChildContainer;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;

abstract class WorkerQueue extends App
{
    /**
     * Bootstrap the application once at worker startup.
     *
     * The returned application is frozen after this call — its container must
     * not be written to again. Pass it to handle() for every subsequent job.
     */
    public static function bootstrap(QueueConfigContract $config): ApplicationContract
    {
        $app = static::start(
            config: $config,
        );

        $container = $app->getContainer();

        static::bootstrapThrowableHandler($app, $container);

        // Force-resolve services that must live in the parent's frozen map.
        static::bootstrapParentServices($app);

        return $app;
    }

    /**
     * Handle a single job using an isolated child application.
     */
    public static function handle(
        ApplicationContract $app,
        ContainerData $data,
        JobContract $job,
        ClientContract $client,
        RequeuerContract $requeuer,
    ): void {
        $childContainer = static::getChildContainer($app, $data);
        $childApp       = static::getChildApplication($app, $childContainer);

        static::bootstrapChildContainer($childApp, $childContainer);

        static::handleJob($childContainer, $job, $client, $requeuer);
    }

    /**
     * Get a child application for the job.
     */
    public static function getChildApplication(ApplicationContract $app, ContainerContract $container): ApplicationContract
    {
        return new ChildApplication($app, $container);
    }

    /**
     * Get a child container for the job.
     */
    public static function getChildContainer(ApplicationContract $app, ContainerData $data): ContainerContract
    {
        $parent = $app->getContainer();

        return new ChildContainer($parent, $data);
    }

    /**
     * Bootstrap a child container with the job-scoped singletons.
     */
    public static function bootstrapChildContainer(ApplicationContract $app, ContainerContract $container): void
    {
        $container->setSingleton(ApplicationContract::class, $app);
        $container->setSingleton(ContainerContract::class, $container);
    }

    /**
     * Handle a single job, settling the outcome between the two always-run stages.
     */
    public static function handleJob(
        ContainerContract $container,
        JobContract $job,
        ClientContract $client,
        RequeuerContract $requeuer,
    ): void {
        $handler = $container->getSingleton(JobHandlerContract::class);

        $result = $handler->run($job);

        $requeuer->settle($job, $result, $client);

        $handler->resultSettled($job, $result);
    }

    /**
     * Force-resolve services that must be pre-built in the parent container.
     *
     * Override in subclasses to eagerly resolve additional services so they are
     * cached in the frozen parent rather than re-created per job.
     */
    public static function bootstrapParentServices(ApplicationContract $app): void
    {
        $app->getContainer()->getSingleton(RouteCollectionContract::class);
    }
}
