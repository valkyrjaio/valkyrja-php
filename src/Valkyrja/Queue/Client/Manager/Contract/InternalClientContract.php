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

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

interface InternalClientContract extends ClientContract
{
    /**
     * Settle an outcome for a job that ran in this process.
     *
     * A client reaches this only where no processor owns redelivery, so the
     * client answers a retry itself rather than signaling a broker.
     */
    public function settle(JobContract $job, JobResult $result): void;
}
