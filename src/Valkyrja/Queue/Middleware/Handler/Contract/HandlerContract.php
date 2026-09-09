<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Middleware\Handler\Contract;

use Valkyrja\Queue\Middleware\Contract\JobReceivedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;

/**
 * @template Middleware of JobReceivedMiddlewareContract|RouteMatchedMiddlewareContract|RouteNotMatchedMiddlewareContract|RouteDispatchedMiddlewareContract|ThrowableCaughtMiddlewareContract|SettlingResultMiddlewareContract|ResultSettledMiddlewareContract
 */
interface HandlerContract
{
    /**
     * @param class-string<Middleware> ...$middleware The middleware to add
     */
    public function add(string ...$middleware): void;
}
