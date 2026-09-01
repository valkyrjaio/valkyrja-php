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
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

final class ResponseSentMiddlewareFixture implements ResponseSentMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function responseSent(ServiceCallContract $call, ServiceResponseContract $response, ResponseSentHandlerContract $handler): void
    {
        $this->updateCounter();

        $handler->responseSent($call, $response);
    }
}
