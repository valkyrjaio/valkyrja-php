<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Manager\Contract;

use Valkyrja\Queue\Message\Job\Contract\JobContract;

interface ClientContract
{
    /**
     * Enqueue a fresh job.
     *
     * The framework stamps the id, producer, and timestamps and ensures the
     * attempt count; the producer supplies only the authorable fields.
     *
     * This is fire-and-forget: it does not await an outcome, which is strictly
     * the consume side. It returns once the processor acknowledges the enqueue
     * and throws on an enqueue error.
     */
    public function push(JobContract $job): void;

    /**
     * Re-enqueue an existing job for retry.
     *
     * This is the settlement seam: the consumer hands over the already
     * incremented job on a retry outcome. Unlike `push` it does not re-stamp
     * the id — which is stable across retries — or reset the attempt count.
     *
     * The hold is supplied by the re-queuer rather than derived here, because
     * it is keyed to the attempt that just failed and this receives the
     * incremented copy. The producer's original `delay_ms` is never re-applied
     * on a retry; it is intent recorded at first publish.
     *
     * @param int<0, max> $delayMs The hold before the job becomes eligible again
     */
    public function retry(JobContract $job, int $delayMs = 0): void;

    /**
     * Get the stamped jobs handed to this client during this unit of work.
     *
     * One primitive, three payoffs: it is the deferred adapter's buffer, the
     * test surface, and per-request observability. It must be scoped to the
     * request/command/job — a process-global record would leak in a
     * long-running server and bleed one request's deferred jobs into the next.
     *
     * @return JobContract[]
     */
    public function getPushed(): array;
}
