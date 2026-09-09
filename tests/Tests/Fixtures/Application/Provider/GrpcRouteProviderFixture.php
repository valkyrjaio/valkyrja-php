<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Provider;

use Override;
use Valkyrja\Grpc\Routing\Provider\Contract\GrpcRouteProviderContract;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\GreeterControllerFixture;

final class GrpcRouteProviderFixture implements GrpcRouteProviderContract
{
    public static bool $called = false;

    /**
     * @inheritDoc
     */
    #[Override]
    public function getControllerClasses(): array
    {
        self::$called = true;

        return [
            GreeterControllerFixture::class,
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
