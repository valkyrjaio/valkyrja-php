<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Server\Middleware\ThrowableCaught;

use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Grpc\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Tests\Fixtures\Throwable\Exception\ValkyrjaRuntimeExceptionFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class LogThrowableCaughtMiddlewareTest extends TestCase
{
    public function testThrowableCaught(): void
    {
        $call      = new ServiceCall('/pkg.Service/Method');
        $response  = ServiceResponse::ok();
        $exception = new ValkyrjaRuntimeExceptionFixture();
        $method    = $call->getMethod();

        $logger = $this->createMock(LoggerContract::class);
        $logger->expects($this->once())
            ->method('throwable')
            ->with(
                self::equalTo($exception),
                self::equalTo("Grpc Server Error\nMethod: $method"),
            );

        $handler = $this->createMock(ThrowableCaughtHandler::class);
        $handler->expects($this->once())
            ->method('throwableCaught')
            ->with(
                self::equalTo($call),
                self::equalTo($response),
                self::equalTo($exception),
            )
            ->willReturn($response);

        $middleware = new LogThrowableCaughtMiddleware(logger: $logger);

        $responseAfterMiddleware = $middleware->throwableCaught($call, $response, $exception, $handler);

        self::assertSame($response, $responseAfterMiddleware);
    }
}
