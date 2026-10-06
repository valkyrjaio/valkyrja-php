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

use Valkyrja\Cli\Middleware\Contract\InputReceivedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\ProcessExitingMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\ThrowableCaughtMiddlewareContract;

interface CliConfigContract extends ConfigContract
{
    /** @var non-empty-string */
    public string $defaultCommandName {
        get;
    }
    /** @var class-string<InputReceivedMiddlewareContract>[] */
    public array $inputReceivedMiddleware {
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
    /** @var class-string<ProcessExitingMiddlewareContract>[] */
    public array $processExitingMiddleware {
        get;
    }
}
