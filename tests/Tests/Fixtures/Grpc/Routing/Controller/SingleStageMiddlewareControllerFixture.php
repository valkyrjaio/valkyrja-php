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
use Valkyrja\Grpc\Routing\Attribute\Method\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;

/**
 * A gRPC service controller whose middleware each serve one stage, so a test can prove the
 * collector leaves the other four buckets empty.
 */
#[Service(service: 'pkg.SingleStage')]
final class SingleStageMiddlewareControllerFixture
{
    #[Method(name: 'Matched')]
    #[Middleware(name: RouteMatchedMiddlewareFixture::class)]
    public static function matched(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok();
    }

    #[Method(name: 'Sent')]
    #[Middleware(name: ResponseSentMiddlewareFixture::class)]
    public static function sent(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok();
    }
}
