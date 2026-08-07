<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry;

use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Entry\Abstract\App;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Client\Requeuer\Requeuer;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;

class Queue extends App
{
    /**
     * Run a single job.
     *
     * Returns nothing, exactly like Http, Cli, and Grpc's run: the outcome is
     * already settled by the time it returns. Tests read a job's life off the
     * per-job result log rather than a return value, which is why a retry chain
     * is distinguishable from an acknowledgement without one.
     */
    public static function run(
        QueueConfigContract $config,
        JobContract $job,
        ClientContract $client,
        RequeuerContract $requeuer = new Requeuer(),
    ): void {
        $app = static::start(
            config: $config,
        );

        $container = $app->getContainer();

        self::bootstrapThrowableHandler($app, $container);

        // The client is framework plumbing, deliberately kept out of the
        // isolated container so job code can never reach it
        $handler = $container->getSingleton(JobHandlerContract::class);

        $result = $handler->run($job);

        $requeuer->settle($job, $result, $client);

        $handler->resultSettled($job, $result);
    }
}
