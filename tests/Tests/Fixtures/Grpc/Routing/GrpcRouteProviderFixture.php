<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Routing;

use Override;
use Valkyrja\Grpc\Routing\Provider\Contract\GrpcRouteProviderContract;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\CounterControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\EchoControllerFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\GreeterControllerFixture;

final class GrpcRouteProviderFixture implements GrpcRouteProviderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function getControllerClasses(): array
    {
        return [
            GreeterControllerFixture::class,
            EchoControllerFixture::class,
            CounterControllerFixture::class,
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRoutes(): array
    {
        return [];
    }
}
