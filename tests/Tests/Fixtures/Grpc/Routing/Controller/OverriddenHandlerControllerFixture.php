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
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Method\MethodHandler;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * A gRPC service controller whose route handler is supplied explicitly rather than derived from the
 * attributed method.
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

    #[Method(name: 'DoThing')]
    #[MethodHandler([self::class, 'actualHandler'])]
    public static function doThing(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('not used');
    }
}
