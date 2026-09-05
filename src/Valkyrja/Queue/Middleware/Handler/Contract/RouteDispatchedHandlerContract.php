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
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

/**
 * @extends HandlerContract<RouteDispatchedMiddlewareContract>
 */
interface RouteDispatchedHandlerContract extends HandlerContract
{
    /**
     * Middleware handler for after a route is dispatched.
     */
    public function routeDispatched(JobContract $job, JobResult $result, RouteContract $route): JobResult;
}
