<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Middleware\Handler\Contract;

use Throwable;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;

/**
 * @extends HandlerContract<ThrowableCaughtMiddlewareContract>
 */
interface ThrowableCaughtHandlerContract extends HandlerContract
{
    /**
     * Middleware handler for when an earlier stage throws.
     */
    public function throwableCaught(ServiceCallContract $call, ServiceResponseContract $response, Throwable $throwable): ServiceResponseContract;
}
