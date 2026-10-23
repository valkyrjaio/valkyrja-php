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
use Valkyrja\Grpc\Message\Status\Status;
use Valkyrja\Grpc\Routing\Attribute\Route;
use Valkyrja\Grpc\Routing\Attribute\Route\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\ThrowableCaughtMiddlewareFixture;

/**
 * A gRPC service controller that declares every field the route attribute takes, so a test can
 * prove the collector carries each argument into the route and appends `#[Middleware]` to the
 * stage list the argument already named.
 *
 * Each stage names its own middleware class, so a test reports an argument the collector wires to
 * the wrong stage.
 */
#[Service(service: 'pkg.Declared')]
final class DeclaredMiddlewareControllerFixture
{
    /** @var non-empty-string */
    public const string DECLARED = 'declared';

    #[Route(
        name: 'EveryStage',
        handler: [self::class, 'declaredHandler'],
        requestType: ServiceResponse::class,
        responseType: Status::class,
        routeMatchedMiddleware: [RouteMatchedMiddlewareFixture::class],
        routeDispatchedMiddleware: [RouteDispatchedMiddlewareFixture::class],
        throwableCaughtMiddleware: [ThrowableCaughtMiddlewareFixture::class],
        sendingResponseMiddleware: [SendingResponseMiddlewareFixture::class],
        responseSentMiddleware: [ResponseSentMiddlewareFixture::class],
    )]
    public static function everyStage(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('not used');
    }

    /**
     * The handler the route attribute names, which is not the attributed method.
     */
    public static function declaredHandler(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok(self::DECLARED);
    }

    #[Route(
        name: 'Appended',
        handler: [self::class, 'appended'],
        routeMatchedMiddleware: [RouteMatchedMiddlewareFixture::class, AllMiddlewareFixture::class],
    )]
    #[Middleware(name: AllMiddlewareFixture::class)]
    public static function appended(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok();
    }
}
