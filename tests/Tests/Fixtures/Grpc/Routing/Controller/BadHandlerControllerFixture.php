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
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * A gRPC service controller whose attributed method returns the wrong type, so the scan-derived
 * handler has to reject it at invocation.
 */
#[Service(service: 'pkg.Bad')]
final class BadHandlerControllerFixture
{
    #[Method(name: 'DoThing')]
    public static function doThing(ContainerContract $container, RouteContract $route): string
    {
        return 'not a response';
    }
}
