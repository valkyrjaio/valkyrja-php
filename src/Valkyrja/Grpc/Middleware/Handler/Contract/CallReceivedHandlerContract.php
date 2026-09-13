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

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\CallReceivedMiddlewareContract;

/**
 * @extends HandlerContract<CallReceivedMiddlewareContract>
 */
interface CallReceivedHandlerContract extends HandlerContract
{
    /**
     * Middleware handler for after a call has been received but before it has been routed.
     */
    public function callReceived(ServiceCallContract $call): ServiceCallContract|ServiceResponseContract;
}
