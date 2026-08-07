<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Dispatcher\Contract;

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

interface RouterContract
{
    /**
     * Resolve a job from the map, then dispatch it.
     */
    public function dispatch(JobContract $job): JobResult;

    /**
     * Dispatch a pre-resolved route.
     */
    public function dispatchRoute(JobContract $job, RouteContract $route): JobResult;
}
