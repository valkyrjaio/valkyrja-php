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
    /** The default cap on messages buffered per call before it is rejected. */
    public const int DEFAULT_MAX_INBOUND_MESSAGES = 1000;

    /**
     * The upper bound on inbound messages per call.
     *
     * The buffered model caps the total messages buffered before dispatch. An over-limit call is
     * rejected with RESOURCE_EXHAUSTED.
     *
     * The streaming model bounds the in-flight window instead, as the high-water mark for
     * backpressure. A larger window raises per-call memory, and it rejects no call.
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
