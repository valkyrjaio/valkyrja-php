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
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\Trait\MiddlewareCounterTrait;

final class ResultSettledMiddlewareChangedFixture implements ResultSettledMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function resultSettled(JobContract $job, JobResult $result, ResultSettledHandlerContract $handler): void
    {
        $this->updateCounter();

        // Do not call the handler, stopping the chain here
    }
}
