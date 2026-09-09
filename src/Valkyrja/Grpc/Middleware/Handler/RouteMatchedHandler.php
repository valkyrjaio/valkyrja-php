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
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Abstract\Handler;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * @extends Handler<RouteMatchedMiddlewareContract>
 */
class RouteMatchedHandler extends Handler implements RouteMatchedHandlerContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function routeMatched(ServiceCallContract $call, RouteContract $route): RouteContract|ServiceResponseContract
    {
        $preCheck = $this->checkCancellation($call);

        if ($preCheck !== null) {
            return $preCheck;
        }

        $next = $this->next;

        if ($next === null) {
            return $route;
        }

        $result = $this->getMiddleware($next)->routeMatched($call, $route, $this);

        $postCheck = $this->checkCancellation(
            $call,
            $result instanceof ServiceResponseContract
                ? $result
                : null
        );

        return $postCheck ?? $result;
    }
}
