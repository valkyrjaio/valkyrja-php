<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Middleware;

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\Trait\MiddlewareCounterTrait;

final class RouteDispatchedMiddlewareChangedFixture implements RouteDispatchedMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function routeDispatched(JobContract $job, JobResult $result, RouteContract $route, RouteDispatchedHandlerContract $handler): JobResult
    {
        $this->updateCounter();

        // Return a different result without calling the handler
        return JobResult::DEAD_LETTER;
    }
}
