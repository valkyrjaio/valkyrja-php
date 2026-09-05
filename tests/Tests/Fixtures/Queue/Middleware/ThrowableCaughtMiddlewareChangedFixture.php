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

use Throwable;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\Trait\MiddlewareCounterTrait;

final class ThrowableCaughtMiddlewareChangedFixture implements ThrowableCaughtMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function throwableCaught(JobContract $job, JobResult $result, Throwable $throwable, ThrowableCaughtHandlerContract $handler): JobResult
    {
        $this->updateCounter();

        // Return a different result without calling the handler
        return JobResult::DEAD_LETTER;
    }
}
