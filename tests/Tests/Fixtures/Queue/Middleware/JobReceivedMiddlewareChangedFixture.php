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
use Valkyrja\Queue\Middleware\Contract\JobReceivedMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\JobReceivedHandlerContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\Trait\MiddlewareCounterTrait;

final class JobReceivedMiddlewareChangedFixture implements JobReceivedMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function jobReceived(JobContract $job, JobReceivedHandlerContract $handler): JobContract|JobResult
    {
        $this->updateCounter();

        // Return a result instead of calling the handler to simulate an early settle
        return JobResult::FAIL;
    }
}
