<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Middleware\Handler;

use Valkyrja\Container\Manager\Container;
use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * The Handler test case.
 */
abstract class HandlerTestCase extends TestCase
{
    protected Container $container;

    protected CancellationToken $cancellation;

    protected ServiceCall $call;

    protected ServiceResponse $response;

    protected RouteContract $route;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->container    = new Container();
        $this->cancellation = new CancellationToken();

        $this->call = new ServiceCall(
            method: '/pkg.Service/Method',
            cancellation: $this->cancellation,
        );

        $this->response = ServiceResponse::ok();

        // The middleware stages take a route but never build one, so a contract double keeps this
        // component's tests independent of the routing component's concrete data class.
        $this->route = self::createStub(RouteContract::class);
    }
}
