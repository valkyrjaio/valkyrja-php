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
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\Trait\MiddlewareCounterTrait;

final class RouteMatchedMiddlewareChangedFixture implements RouteMatchedMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function routeMatched(JobContract $job, RouteContract $route, RouteMatchedHandlerContract $handler): RouteContract|JobResult
    {
        $this->updateCounter();

        // Return a result instead of calling the handler to simulate an early settle
        return JobResult::FAIL;
    }
}
