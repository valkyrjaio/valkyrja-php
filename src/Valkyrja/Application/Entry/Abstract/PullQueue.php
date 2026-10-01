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
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Support\Time\Microtime;

abstract class PullQueue extends WorkerQueue
{
    /**
     * Consume jobs until the loop is stopped or its bounds are reached.
     *
     * @param int<0, max> $maxJobs    The number of jobs to handle before exiting; 0 for no bound
     * @param int<0, max> $maxSeconds The seconds to run before exiting; 0 for no bound
     */
    public static function run(
        QueueConfigContract $config,
        int $maxJobs = 0,
        int $maxSeconds = 0,
    ): void {
        $app = static::bootstrap($config);

        static::loop($app, $maxJobs, $maxSeconds);
    }

    /**
     * Drive the poll loop.
     *
     * @param int<0, max> $maxJobs    The number of jobs to handle before exiting; 0 for no bound
     * @param int<0, max> $maxSeconds The seconds to run before exiting; 0 for no bound
     */
    public static function loop(
        ApplicationContract $app,
        int $maxJobs = 0,
        int $maxSeconds = 0,
    ): void {
        $container = $app->getContainer();
        $data      = $container->getSingleton(ContainerData::class);
        $client    = $container->getSingleton(ClientContract::class);
        $handled   = 0;
        $deadline  = $maxSeconds > 0
            ? Microtime::get() + (float) $maxSeconds
            : 0.0;

        static::connect($app);

        try {
            while (! static::shouldStop($handled, $maxJobs, $deadline)) {
                $job = static::receive();

                if ($job === null) {
                    continue;
                }

                // One job is the unit of work of a worker, so the record of
                // pushed jobs ends with each job rather than with the process.
                // Clearing before the job rather than after leaves the record of
                // the last one readable once the loop exits.
                $client->clearPushed();

                static::handle($app, $data, $job, $client);

                $handled++;
            }
        } finally {
            static::disconnect();
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

    /**
     * Connect to the processor, reading what it needs from the application.
     */
    abstract public static function connect(ApplicationContract $app): void;

    /**
     * Wait for the next job, or return null when nothing arrived in time.
     */
    abstract public static function receive(): JobContract|null;

    /**
     * Disconnect from the processor.
     */
    abstract public static function disconnect(): void;
}
