<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Middleware\Contract;

use Throwable;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;

interface ThrowableCaughtMiddlewareContract
{
    /**
     * Middleware handler for after a throwable was caught, translating it to an outcome.
     */
    public function throwableCaught(
        JobContract $job,
        JobResult $result,
        Throwable $throwable,
        ThrowableCaughtHandlerContract $handler
    ): JobResult;
}
