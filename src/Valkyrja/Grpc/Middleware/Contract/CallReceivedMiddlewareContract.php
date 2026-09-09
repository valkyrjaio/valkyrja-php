<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Middleware\Contract;

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\CallReceivedHandlerContract;

interface CallReceivedMiddlewareContract
{
    /**
     * Middleware handler for after a call has been received but before it has been routed.
     *
     * Returns the (possibly updated) call to continue routing, or a response that short-circuits
     * the pipeline.
     */
    public function callReceived(ServiceCallContract $call, CallReceivedHandlerContract $handler): ServiceCallContract|ServiceResponseContract;
}
