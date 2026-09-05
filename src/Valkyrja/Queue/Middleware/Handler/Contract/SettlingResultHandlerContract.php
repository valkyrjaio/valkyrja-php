<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Middleware\Handler\Contract;

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;

/**
 * @extends HandlerContract<SettlingResultMiddlewareContract>
 */
interface SettlingResultHandlerContract extends HandlerContract
{
    /**
     * Middleware handler ran before the adapter settles the outcome with the processor.
     */
    public function settlingResult(JobContract $job, JobResult $result): JobResult;
}
