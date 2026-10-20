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
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Message\Status\Status;
use Valkyrja\Grpc\Middleware\Contract\CallReceivedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\CallReceivedHandlerContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

final class CallReceivedMiddlewareChangedFixture implements CallReceivedMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function callReceived(ServiceCallContract $call, CallReceivedHandlerContract $handler): ServiceCallContract|ServiceResponseContract
    {
        $this->updateCounter();

        // Return a response without calling the handler to short-circuit the remainder of the chain.
        return ServiceResponse::of(Status::aborted('short-circuited'));
    }
}
