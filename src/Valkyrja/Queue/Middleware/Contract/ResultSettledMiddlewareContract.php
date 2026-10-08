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

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;

interface ResultSettledMiddlewareContract
{
    /**
     * Middleware handler for after the adapter has settled the outcome.
     */
    public function resultSettled(
        JobContract $job,
        JobResult $result,
        ResultSettledHandlerContract $handler
    ): void;
}
