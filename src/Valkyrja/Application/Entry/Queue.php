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
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;

class Queue extends App
{
    /**
     * Run a single job.
     *
     * Settles nothing with a processor, because which signal settles an outcome
     * is processor-specific. A caller that needs settlement boots the entry of
     * its processor. Tests read a job's life off the per-job result log rather
     * than a return value, which is why a retry chain is distinguishable from
     * an acknowledgement without one.
     */
    public static function run(
        QueueConfigContract $config,
        JobContract $job,
    ): void {
        $app = static::start(
            config: $config,
        );

        $container = $app->getContainer();

        self::bootstrapThrowableHandler($app, $container);

        $handler = $container->getSingleton(JobHandlerContract::class);

        $result = $handler->run($job);

        $handler->resultSettled($job, $result);
    }
}
