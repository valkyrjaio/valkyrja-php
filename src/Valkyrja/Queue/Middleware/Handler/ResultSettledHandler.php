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
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Abstract\Handler;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;

/**
 * @extends Handler<ResultSettledMiddlewareContract>
 */
class ResultSettledHandler extends Handler implements ResultSettledHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function resultSettled(JobContract $job, JobResult $result): void
    {
        $next = $this->next;

        if ($next !== null) {
            $this->getMiddleware($next)->resultSettled($job, $result, $this);
        }
    }
}
