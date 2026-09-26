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
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;

/**
 * @extends HandlerContract<SendingResponseMiddlewareContract>
 */
interface SendingResponseHandlerContract extends HandlerContract
{
    /**
     * Middleware handler for before the adapter writes the response to the wire.
     */
    public function sendingResponse(ServiceCallContract $call, ServiceResponseContract $response): ServiceResponseContract;
}
