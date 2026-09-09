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
use Throwable;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Abstract\Handler;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;

/**
 * @extends Handler<ThrowableCaughtMiddlewareContract>
 */
class ThrowableCaughtHandler extends Handler implements ThrowableCaughtHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function throwableCaught(ServiceCallContract $call, ServiceResponseContract $response, Throwable $throwable): ServiceResponseContract
    {
        $preCheck = $this->checkCancellation($call, $response);

        if ($preCheck !== null) {
            return $preCheck;
        }

        $next = $this->next;

        if ($next === null) {
            return $response;
        }

        $returned = $this->getMiddleware($next)->throwableCaught($call, $response, $throwable, $this);

        return $this->checkCancellation($call, $returned) ?? $returned;
    }
}
