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
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;

/**
 * A gRPC service controller covering the unary, streaming-flag, and per-route middleware shapes the
 * attribute collector has to translate.
 */
#[Service(service: 'pkg.Greeter')]
final class GreeterControllerFixture
{
    /** @var non-empty-string */
    public const string SERVICE = 'pkg.Greeter';

    #[Method(name: 'SayHello')]
    public static function sayHello(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('hello');
    }

    #[Method(name: 'Chat', clientStreaming: true, serverStreaming: true)]
    public static function chat(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('chat');
    }

    #[Method(name: 'Guarded')]
    #[Middleware(name: AllMiddlewareFixture::class)]
    public static function guarded(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('guarded');
    }

    /**
     * Not attributed, so the scan must skip it.
     */
    public static function notAnRpc(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('skipped');
    }
}
