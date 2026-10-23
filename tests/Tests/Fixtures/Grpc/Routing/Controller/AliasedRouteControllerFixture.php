<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Routing\Controller;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Attribute\Route;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * A gRPC service controller whose one method answers two remote methods, so a test can prove the
 * collector reads a repeated route attribute as a route for each name.
 */
#[Service(service: 'pkg.Aliased')]
final class AliasedRouteControllerFixture
{
    #[Route(name: 'SayHello', handler: [self::class, 'sayHello'])]
    #[Route(name: 'Greet', handler: [self::class, 'sayHello'], serverStreaming: true)]
    public static function sayHello(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('hello');
    }
}
