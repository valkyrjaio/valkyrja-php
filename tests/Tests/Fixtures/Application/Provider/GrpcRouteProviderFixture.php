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

final class GrpcRouteProviderFixture implements GrpcRouteProviderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function getControllerClasses(): array
    {
        return [];
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
