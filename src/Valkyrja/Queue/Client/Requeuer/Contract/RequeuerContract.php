<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Requeuer\Contract;

use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

interface RequeuerContract
{
    /**
     * Settle an outcome with the processor.
     */
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void;
}
