<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Entry;

use Override;
use Valkyrja\Application\Entry\Abstract\PullQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

use function array_shift;

/**
 * A pull entry that yields a scripted sequence of deliveries, then nothing.
 *
 * A null stands for a poll that timed out, which is what lets a test drive the
 * loop through the branch where nothing arrived.
 */
final class ScriptedQueueFixture extends PullQueue
{
    public static bool $connected = false;

    public static int $receiveCount = 0;

    /** @var array<int, JobContract|null> */
    private static array $deliveries = [];

    /**
     * Script what the next polls hand back.
     *
     * @param array<int, JobContract|null> $deliveries The scripted deliveries
     */
    public static function script(array $deliveries): void
    {
        self::$deliveries   = $deliveries;
        self::$receiveCount = 0;
        self::$connected    = false;
    }

    /**
     * Drop the script, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$deliveries   = [];
        self::$receiveCount = 0;
        self::$connected    = false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function connect(ApplicationContract $app): void
    {
        self::$connected = true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function receive(): JobContract|null
    {
        self::$receiveCount++;

        return array_shift(self::$deliveries);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function disconnect(): void
    {
        self::$connected = false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        // Stands in for a processor with no native redelivery
        if ($result === JobResult::RETRY) {
            $client->requeue($job);
        }
    }
}
