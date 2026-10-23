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
use Valkyrja\Grpc\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * A gRPC service controller whose handler comes from `#[RouteHandler]`, so a test can prove that
 * spelling wins over the route attribute's own handler argument, and that it supplies the handler
 * of a route whose attribute names none.
 */
#[Service(service: 'pkg.Overridden')]
final class OverriddenHandlerControllerFixture
{
    /** @var non-empty-string */
    public const string OVERRIDDEN = 'overridden';

    public static function actualHandler(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok(self::OVERRIDDEN);
    }

    #[Route(name: 'DoThing', handler: [self::class, 'doThing'])]
    #[RouteHandler([self::class, 'actualHandler'])]
    public static function doThing(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('not used');
    }

    #[Route(name: 'DoInstanceThing')]
    #[RouteHandler([self::class, 'actualHandler'])]
    public function doInstanceThing(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('not used either');
    }
}
