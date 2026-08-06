<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Middleware\Handler;

use Override;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Abstract\Handler;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

/**
 * @extends Handler<RouteDispatchedMiddlewareContract>
 */
class RouteDispatchedHandler extends Handler implements RouteDispatchedHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function routeDispatched(JobContract $job, JobResult $result, RouteContract $route): JobResult
    {
        $next = $this->next;

        return $next !== null
            ? $this->getMiddleware($next)->routeDispatched($job, $result, $route, $this)
            : $result;
    }
}
