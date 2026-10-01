<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Data\Contract;

use Valkyrja\Grpc\Middleware\Contract\CallReceivedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;

interface GrpcConfigContract extends ConfigContract
{
    /** The default cap on the inbound messages an adapter holds for one call. */
    public const int DEFAULT_MAX_INBOUND_MESSAGES = 1000;

    /**
     * The upper bound on inbound messages per call, which the adapter enforces.
     *
     * Under the buffered model the adapter caps the messages it buffers before dispatch, and the
     * adapter rejects an over-limit call with RESOURCE_EXHAUSTED.
     *
     * Under the streaming model the adapter reads the same number as the high-water mark of the
     * live inbound queue. The adapter pauses the transport at that mark and resumes as the handler
     * drains, and the adapter rejects no call. A higher mark raises the memory one call holds.
     *
     * @var positive-int
     */
    public int $maxInboundMessages {
        get;
    }
    /** @var class-string<CallReceivedMiddlewareContract>[] */
    public array $callReceivedMiddleware {
        get;
    }
    /** @var class-string<RouteMatchedMiddlewareContract>[] */
    public array $routeMatchedMiddleware {
        get;
    }
    /** @var class-string<RouteNotMatchedMiddlewareContract>[] */
    public array $routeNotMatchedMiddleware {
        get;
    }
    /** @var class-string<RouteDispatchedMiddlewareContract>[] */
    public array $routeDispatchedMiddleware {
        get;
    }
    /** @var class-string<ThrowableCaughtMiddlewareContract>[] */
    public array $throwableCaughtMiddleware {
        get;
    }
    /** @var class-string<SendingResponseMiddlewareContract>[] */
    public array $sendingResponseMiddleware {
        get;
    }
    /** @var class-string<ResponseSentMiddlewareContract>[] */
    public array $responseSentMiddleware {
        get;
    }
}
