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
use Valkyrja\Grpc\Routing\Attribute\Route\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareFixture;

/**
 * A gRPC service controller whose middleware each serve one stage, so a test can prove the
 * collector reads the stage from the contracts the class implements and leaves the other four
 * buckets empty.
 */
#[Service(service: 'pkg.SingleStage')]
final class SingleStageMiddlewareControllerFixture
{
    #[Route(name: 'Matched', handler: [self::class, 'matched'])]
    #[Middleware(name: RouteMatchedMiddlewareFixture::class)]
    public static function matched(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok();
    }

    #[Route(name: 'Sent', handler: [self::class, 'sent'])]
    #[Middleware(name: ResponseSentMiddlewareFixture::class)]
    public static function sent(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok();
    }

    #[Route(name: 'Dispatched', handler: [self::class, 'dispatched'])]
    #[Middleware(name: RouteDispatchedMiddlewareFixture::class)]
    public static function dispatched(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok();
    }

    #[Route(name: 'Caught', handler: [self::class, 'caught'])]
    #[Middleware(name: ThrowableCaughtMiddlewareFixture::class)]
    public static function caught(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok();
    }

    #[Route(name: 'Sending', handler: [self::class, 'sending'])]
    #[Middleware(name: SendingResponseMiddlewareFixture::class)]
    public static function sending(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok();
    }
}
