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
use Valkyrja\Queue\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;

interface RouteNotMatchedMiddlewareContract
{
    /**
     * Middleware handler for after a route has not been matched.
     */
    public function routeNotMatched(
        JobContract $job,
        JobResult $result,
        RouteNotMatchedHandlerContract $handler
    ): JobResult;
}
