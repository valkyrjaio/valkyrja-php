<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Middleware;

use RuntimeException;
use Throwable;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Tests\Fixtures\Grpc\Middleware\Trait\MiddlewareCounterTrait;

/**
 * Throws from the ThrowableCaught stage, so a test can drive the handler's recovery guard.
 */
final class ThrowableCaughtMiddlewareThrowingFixture implements ThrowableCaughtMiddlewareContract
{
    use MiddlewareCounterTrait;

    /** @var non-empty-string */
    public const string MESSAGE = 'the recovery itself failed';

    /**
     * @throws Throwable
     */
    public function throwableCaught(ServiceCallContract $call, ServiceResponseContract $response, Throwable $throwable, ThrowableCaughtHandlerContract $handler): ServiceResponseContract
    {
        $this->updateCounter();

        throw new RuntimeException(self::MESSAGE);
    }
}
