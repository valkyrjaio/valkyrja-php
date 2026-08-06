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
use Valkyrja\Queue\Middleware\Contract\JobReceivedMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Abstract\Handler;
use Valkyrja\Queue\Middleware\Handler\Contract\JobReceivedHandlerContract;

/**
 * @extends Handler<JobReceivedMiddlewareContract>
 */
class JobReceivedHandler extends Handler implements JobReceivedHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function jobReceived(JobContract $job): JobContract|JobResult
    {
        $next = $this->next;

        return $next !== null
            ? $this->getMiddleware($next)->jobReceived($job, $this)
            : $job;
    }
}
