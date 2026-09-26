<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Data;

use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Data\GrpcRoutingData;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class GrpcRoutingDataTest extends TestCase
{
    public function testDefaultsToNoRoutes(): void
    {
        self::assertSame([], new GrpcRoutingData()->routes);
    }

    public function testHoldsSuppliedRoutes(): void
    {
        $route = new Route('/pkg.Service/Method', static fn (): ServiceResponse => ServiceResponse::ok());

        $data = new GrpcRoutingData(['/pkg.Service/Method' => static fn (): RouteContract => $route]);

        self::assertArrayHasKey('/pkg.Service/Method', $data->routes);
        self::assertSame($route, ($data->routes['/pkg.Service/Method'])());
    }
}
