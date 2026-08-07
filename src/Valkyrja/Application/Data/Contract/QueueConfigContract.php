<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Data\Contract;

use Valkyrja\Queue\Middleware\Contract\JobReceivedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;

interface QueueConfigContract extends ConfigContract
{
    /** @var non-empty-string */
    public string $applicationName {
        get;
    }
    /**
     * The default ceiling before a retry chain is dead-lettered.
     *
     * @var positive-int
     */
    public int $defaultMaxAttempts {
        get;
    }
    /**
     * The default hold, in milliseconds, before a retry re-enqueue.
     *
     * A zero is allowed but bad: it retries immediately, giving a failing
     * dependency no time to recover.
     *
     * @var int<0, max>
     */
    public int $defaultRetryDelayMs {
        get;
    }
    public bool $defaultRetryDelayMultiplyByAttempt {
        get;
    }
    /** @var class-string<JobReceivedMiddlewareContract>[] */
    public array $jobReceivedMiddleware {
        get;
    }
    /** @var class-string<RouteMatchedMiddlewareContract>[] */
    public array $routeMatchedMiddleware {
        get;
    }
    /** @var class-string<RouteNotMatchedMiddlewareContract>[] */
    public array $routeNotMatchedMiddleware {
        get;
    }
    /** @var class-string<RouteDispatchedMiddlewareContract>[] */
    public array $routeDispatchedMiddleware {
        get;
    }
    /** @var class-string<ThrowableCaughtMiddlewareContract>[] */
    public array $throwableCaughtMiddleware {
        get;
    }
    /** @var class-string<SettlingResultMiddlewareContract>[] */
    public array $settlingResultMiddleware {
        get;
    }
    /** @var class-string<ResultSettledMiddlewareContract>[] */
    public array $resultSettledMiddleware {
        get;
    }
}
