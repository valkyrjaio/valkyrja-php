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

use Throwable;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

final class ThrowableCaughtMiddlewareFixture implements ThrowableCaughtMiddlewareContract
{
    use MiddlewareCounterTrait;

    public function throwableCaught(ServiceCallContract $call, ServiceResponseContract $response, Throwable $throwable, ThrowableCaughtHandlerContract $handler): ServiceResponseContract
    {
        $this->updateCounter();

        return $handler->throwableCaught($call, $response, $throwable);
    }
}
