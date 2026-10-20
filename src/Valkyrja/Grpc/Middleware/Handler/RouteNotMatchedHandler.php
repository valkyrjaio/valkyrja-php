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
use Valkyrja\Grpc\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Abstract\Handler;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;

/**
 * @extends Handler<RouteNotMatchedMiddlewareContract>
 */
class RouteNotMatchedHandler extends Handler implements RouteNotMatchedHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function routeNotMatched(ServiceCallContract $call, ServiceResponseContract $response): ServiceResponseContract
    {
        $preCheck = $this->checkCancellation($call, $response);

        if ($preCheck !== null) {
            return $preCheck;
        }

        $next = $this->next;

        if ($next === null) {
            return $response;
        }

        $returned = $this->getMiddleware($next)->routeNotMatched($call, $response, $this);

        return $this->checkCancellation($call, $returned) ?? $returned;
    }
}
