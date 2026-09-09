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

use Throwable;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;

/**
 * @extends HandlerContract<ThrowableCaughtMiddlewareContract>
 */
interface ThrowableCaughtHandlerContract extends HandlerContract
{
    /**
     * Middleware handler for after a throwable was caught, translating it to an outcome.
     */
    public function throwableCaught(JobContract $job, JobResult $result, Throwable $throwable): JobResult;
}
