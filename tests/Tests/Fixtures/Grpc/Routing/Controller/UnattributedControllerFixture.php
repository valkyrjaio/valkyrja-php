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
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * Carries a method attribute but no service attribute, so the scan must skip the class entirely.
 */
final class UnattributedControllerFixture
{
    #[Method(name: 'Orphan')]
    public static function orphan(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('orphan');
    }
}
