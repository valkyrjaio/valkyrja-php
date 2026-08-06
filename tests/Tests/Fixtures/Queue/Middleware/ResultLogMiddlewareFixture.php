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
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;

/**
 * The per-job result log.
 *
 * An outcome is never returned to the caller — settlement acts on it directly —
 * so distinguishing the four outcomes after the fact is a testing concern only.
 * This keeps an in-memory job-id to outcomes map so a job's whole life reads
 * back as `[ACK]`, `[FAIL]`, or `[RETRY, RETRY, DEAD_LETTER]`.
 *
 * It exists specifically to test the middleware, the clients, and the entry
 * classes. It is not a production mechanism, and it is separate from settlement
 * itself.
 */
final class ResultLogMiddlewareFixture implements ResultSettledMiddlewareContract
{
    /** @var array<string, JobResult[]> */
    private static array $log = [];

    /**
     * Get the outcomes recorded for a job, in order.
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
     * Get the whole log.
     *
     * @return array<string, JobResult[]>
     */
    public static function getLog(): array
    {
        return self::$log;
    }

    /**
     * Reset the log.
     */
    public static function reset(): void
    {
        self::$log = [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function resultSettled(JobContract $job, JobResult $result, ResultSettledHandlerContract $handler): void
    {
        self::$log[$job->getId()][] = $result;

        $handler->resultSettled($job, $result);
    }
}
