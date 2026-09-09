<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Middleware\ThrowableCaught;

use Override;
use Throwable;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;

class LogThrowableCaughtMiddleware implements ThrowableCaughtMiddlewareContract
{
    public function __construct(
        protected LoggerContract $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function throwableCaught(
        JobContract $job,
        JobResult $result,
        Throwable $throwable,
        ThrowableCaughtHandlerContract $handler
    ): JobResult {
        $name     = $job->getName();
        $id       = $job->getId();
        $attempts = $job->getAttempts();
        $max      = $job->getMaxAttempts();

        $this->logger->throwable($throwable, "Queue Job Error\nJob: $name\nId: $id\nAttempt: $attempts/$max");

        return $handler->throwableCaught($job, $result, $throwable);
    }
}
