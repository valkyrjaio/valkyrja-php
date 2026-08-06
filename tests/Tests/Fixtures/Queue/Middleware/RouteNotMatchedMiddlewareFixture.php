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
use Valkyrja\Queue\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\Trait\MiddlewareCounterTrait;

final class RouteNotMatchedMiddlewareFixture implements RouteNotMatchedMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function routeNotMatched(JobContract $job, JobResult $result, RouteNotMatchedHandlerContract $handler): JobResult
    {
        $this->updateCounter();

        return $handler->routeNotMatched($job, $result);
    }
}
