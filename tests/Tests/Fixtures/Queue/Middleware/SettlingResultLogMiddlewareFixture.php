<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Middleware;

use Override;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\SettlingResultHandlerContract;

/**
 * The per-job log of the outcome the settling stage was handed.
 *
 * `ResultLogMiddlewareFixture` records the ResultSettled stage, which reads the
 * same outcome whether a caller caps before or after settlement. This one reads
 * the stage whose job is the last word on the outcome, so a cap applied too late
 * shows up as a different entry.
 *
 * It exists to test the ordering only, and it is not a production mechanism.
 */
final class SettlingResultLogMiddlewareFixture implements SettlingResultMiddlewareContract
{
    /** @var array<string, JobResult[]> */
    private static array $log = [];

    /**
     * Get the outcomes the settling stage saw for a job, in order.
     *
     * @param non-empty-string $id The job id
     *
     * @return JobResult[]
     */
    public static function getResults(string $id): array
    {
        return self::$log[$id] ?? [];
    }

    /**
     * Drop the log, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$log = [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settlingResult(
        JobContract $job,
        JobResult $result,
        SettlingResultHandlerContract $handler
    ): JobResult {
        self::$log[$job->getId()][] = $result;

        return $handler->settlingResult($job, $result);
    }
}
