<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Enum;

enum JobResult: string
{
    /** Processed successfully; remove from the queue. */
    case ACK = 'ack';
    /** Put back for redelivery after the job's retry delay. */
    case RETRY = 'retry';
    /** The handler gave up on purpose; dead-letter now, no retries. */
    case FAIL = 'fail';
    /** The framework exhausted max attempts on a retry chain; dead-letter. */
    case DEAD_LETTER = 'dead_letter';

    /**
     * Determine whether this outcome ends the job's life.
     *
     * Every outcome but a retry is terminal — there is nothing further to do to
     * the job itself once it is acknowledged, failed, or dead-lettered.
     */
    public function isTerminal(): bool
    {
        return $this !== self::RETRY;
    }

    /**
     * Determine whether this outcome routes the job to the dead-letter destination.
     */
    public function isDeadLettered(): bool
    {
        return $this === self::FAIL || $this === self::DEAD_LETTER;
    }
}
