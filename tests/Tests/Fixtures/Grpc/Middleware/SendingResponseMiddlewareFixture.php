<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Middleware;

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

final class SendingResponseMiddlewareFixture implements SendingResponseMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function sendingResponse(ServiceCallContract $call, ServiceResponseContract $response, SendingResponseHandlerContract $handler): ServiceResponseContract
    {
        $this->updateCounter();

        return $handler->sendingResponse($call, $response);
    }
}
