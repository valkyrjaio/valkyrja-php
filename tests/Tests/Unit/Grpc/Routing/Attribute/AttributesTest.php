<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Attribute;

use ReflectionMethod;
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Method\MethodHandler;
use Valkyrja\Grpc\Routing\Attribute\Method\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Grpc\Routing\Controller\GreeterControllerFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class AttributesTest extends TestCase
{
    public function testService(): void
    {
        self::assertSame('pkg.Greeter', new Service('pkg.Greeter')->service);
    }

    public function testMethodDefaults(): void
    {
        $method = new Method('SayHello');

        self::assertSame('SayHello', $method->name);
        self::assertFalse($method->clientStreaming);
        self::assertFalse($method->serverStreaming);
        self::assertNull($method->requestType);
        self::assertNull($method->responseType);
    }

    public function testMethodWithEverything(): void
    {
        $method = new Method(
            name: 'Chat',
            clientStreaming: true,
            serverStreaming: true,
            requestType: Route::class,
            responseType: Route::class,
        );

        self::assertTrue($method->clientStreaming);
        self::assertTrue($method->serverStreaming);
        self::assertSame(Route::class, $method->requestType);
        self::assertSame(Route::class, $method->responseType);
    }

    public function testMethodCarriesItsReflection(): void
    {
        $method     = new Method('SayHello');
        $reflection = new ReflectionMethod(GreeterControllerFixture::class, 'sayHello');

        $method->setReflection($reflection);

        self::assertSame($reflection, $method->getReflection());
    }

    public function testMiddleware(): void
    {
        self::assertSame(AllMiddlewareFixture::class, new Middleware(AllMiddlewareFixture::class)->name);
    }

    public function testMethodHandler(): void
    {
        $handler = [GreeterControllerFixture::class, 'sayHello'];

        self::assertSame($handler, new MethodHandler($handler)->handler);
    }
}
