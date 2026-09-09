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
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Abstract\Handler;
use Valkyrja\Grpc\Middleware\Handler\Contract\ResponseSentHandlerContract;

/**
 * @extends Handler<ResponseSentMiddlewareContract>
 */
class ResponseSentHandler extends Handler implements ResponseSentHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function responseSent(ServiceCallContract $call, ServiceResponseContract $response): void
    {
        $next = $this->next;

        if ($next !== null) {
            $this->getMiddleware($next)->responseSent($call, $response, $this);
        }
    }
}
