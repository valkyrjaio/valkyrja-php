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
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

/**
 * Passes a different route down the chain, so a test can prove what the router does with it.
 */
final class RouteMatchedMiddlewareReplacingRouteFixture implements RouteMatchedMiddlewareContract
{
    use MiddlewareCounterTrait;

    /** @var non-empty-string */
    public const string REPLACEMENT_METHOD = '/pkg.Service/Replacement';

    public function routeMatched(ServiceCallContract $call, RouteContract $route, RouteMatchedHandlerContract $handler): RouteContract|ServiceResponseContract
    {
        $this->updateCounter();

        $replacement = new Route(
            method: self::REPLACEMENT_METHOD,
            handler: static fn (): ServiceResponseContract => ServiceResponse::ok('replaced'),
        );

        return $handler->routeMatched($call, $replacement);
    }
}
