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
use Valkyrja\Grpc\Middleware\Contract\CallReceivedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Abstract\Handler;
use Valkyrja\Grpc\Middleware\Handler\Contract\CallReceivedHandlerContract;

/**
 * @extends Handler<CallReceivedMiddlewareContract>
 */
class CallReceivedHandler extends Handler implements CallReceivedHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function callReceived(ServiceCallContract $call): ServiceCallContract|ServiceResponseContract
    {
        $preCheck = $this->checkCancellation($call);

        if ($preCheck !== null) {
            return $preCheck;
        }

        $next = $this->next;

        if ($next === null) {
            return $call;
        }

        $result = $this->getMiddleware($next)->callReceived($call, $this);

        $postCheck = $this->checkCancellation(
            $call,
            $result instanceof ServiceResponseContract
                ? $result
                : null
        );

        return $postCheck ?? $result;
    }
}
