<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler;

use Override;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

final class RouteDispatchedHandlerFixture extends RouteDispatchedHandler
{
    protected int $count = 0;

    /**
     * Get the count of calls.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routeDispatched(ServiceCallContract $call, ServiceResponseContract $response, RouteContract $route): ServiceResponseContract
    {
        $this->count++;

        return parent::routeDispatched($call, $response, $route);
    }
}
