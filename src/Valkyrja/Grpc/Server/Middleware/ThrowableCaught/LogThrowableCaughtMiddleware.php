<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Server\Middleware\ThrowableCaught;

use Override;
use Throwable;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;

class LogThrowableCaughtMiddleware implements ThrowableCaughtMiddlewareContract
{
    public function __construct(
        protected LoggerContract $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function throwableCaught(ServiceCallContract $call, ServiceResponseContract $response, Throwable $throwable, ThrowableCaughtHandlerContract $handler): ServiceResponseContract
    {
        $method     = $call->getMethod();
        $logMessage = "Grpc Server Error\nMethod: $method";

        $this->logger->throwable($throwable, $logMessage);

        return $handler->throwableCaught($call, $response, $throwable);
    }
}
