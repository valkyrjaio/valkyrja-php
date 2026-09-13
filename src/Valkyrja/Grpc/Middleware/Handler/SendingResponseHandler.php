<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Middleware\Handler;

use Override;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Abstract\Handler;
use Valkyrja\Grpc\Middleware\Handler\Contract\SendingResponseHandlerContract;

/**
 * @extends Handler<SendingResponseMiddlewareContract>
 */
class SendingResponseHandler extends Handler implements SendingResponseHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function sendingResponse(ServiceCallContract $call, ServiceResponseContract $response): ServiceResponseContract
    {
        $next = $this->next;

        return $next !== null
            ? $this->getMiddleware($next)->sendingResponse($call, $response, $this)
            : $response;
    }
}
