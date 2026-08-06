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
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Grpc\Routing\Provider\Contract\GrpcRouteProviderContract;

final class GrpcRouteProviderWithRoutesFixture implements GrpcRouteProviderContract
{
    /** @var non-empty-string */
    public const string METHOD = '/pkg.Prebuilt/DoThing';

    public static function handler(): ServiceResponseContract
    {
        return ServiceResponse::ok('prebuilt');
    }

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
        return [
            new Route(self::METHOD, [self::class, 'handler']),
        ];
    }
}
