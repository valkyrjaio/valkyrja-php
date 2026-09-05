<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Middleware\Handler;

use Override;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

final class RouteDispatchedHandlerFixture extends RouteDispatchedHandler
{
    protected int $count = 0;

    /**
     * Get the count of calls.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routeDispatched(JobContract $job, JobResult $result, RouteContract $route): JobResult
    {
        $this->count++;

        return parent::routeDispatched($job, $result, $route);
    }
}
