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
use Valkyrja\Application\Entry\Abstract\WorkerQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Puller\Contract\PullerContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Client\Requeuer\Requeuer;
use Valkyrja\Support\Time\Microtime;

class PullQueue extends WorkerQueue
{
    /**
     * Consume jobs until the loop is stopped or its bounds are reached.
     *
     * @param int<0, max> $maxJobs    The number of jobs to handle before exiting; 0 for no bound
     * @param int<0, max> $maxSeconds The seconds to run before exiting; 0 for no bound
     */
    public static function run(
        QueueConfigContract $config,
        PullerContract $puller,
        ClientContract $client,
        int $maxJobs = 0,
        int $maxSeconds = 0,
        RequeuerContract $requeuer = new Requeuer(),
    ): void {
        $app = static::bootstrap($config);

        static::loop($app, $puller, $client, $maxJobs, $maxSeconds, $requeuer);
    }

    /**
     * Drive the poll loop.
     *
     * @param int<0, max> $maxJobs    The number of jobs to handle before exiting; 0 for no bound
     * @param int<0, max> $maxSeconds The seconds to run before exiting; 0 for no bound
     */
    public static function loop(
        ApplicationContract $app,
        PullerContract $puller,
        ClientContract $client,
        int $maxJobs = 0,
        int $maxSeconds = 0,
        RequeuerContract $requeuer = new Requeuer(),
    ): void {
        $data     = $app->getContainer()->getSingleton(ContainerData::class);
        $handled  = 0;
        $deadline = $maxSeconds > 0
            ? Microtime::get() + (float) $maxSeconds
            : 0.0;

        $puller->connect();

        try {
            while (! static::shouldStop($handled, $maxJobs, $deadline)) {
                $job = $puller->receive();

                if ($job === null) {
                    continue;
                }

                static::handle($app, $data, $job, $client, $requeuer);

                $handled++;
            }
        } finally {
            $puller->disconnect();
        }
    }

    /**
     * Determine whether the loop has reached one of its bounds.
     *
     * @param int<0, max> $handled  The jobs handled so far
     * @param int<0, max> $maxJobs  The job bound; 0 for no bound
     * @param float       $deadline The wall-clock deadline; 0.0 for no bound
     */
    public static function shouldStop(int $handled, int $maxJobs, float $deadline): bool
    {
        if ($maxJobs > 0 && $handled >= $maxJobs) {
            return true;
        }

        return $deadline > 0.0 && Microtime::get() >= $deadline;
    }
}
