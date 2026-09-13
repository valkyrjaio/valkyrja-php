<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Data;

use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Grpc\Middleware\Contract\CallReceivedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;

final class GrpcConfigFixture implements GrpcConfigContract
{
    /**
     * @param non-empty-string                                  $namespace
     * @param non-empty-string                                  $dir
     * @param non-empty-string                                  $version
     * @param non-empty-string                                  $environment
     * @param non-empty-string                                  $timezone
     * @param non-empty-string                                  $key
     * @param non-empty-string                                  $dataPath
     * @param non-empty-string                                  $dataNamespace
     * @param positive-int                                      $port
     * @param positive-int                                      $maxInboundMessages
     * @param ComponentProviderContract[]                       $providers
     * @param array<callable(ApplicationContract):void>         $callbacks
     * @param class-string<CallReceivedMiddlewareContract>[]    $callReceivedMiddleware
     * @param class-string<RouteMatchedMiddlewareContract>[]    $routeMatchedMiddleware
     * @param class-string<RouteNotMatchedMiddlewareContract>[] $routeNotMatchedMiddleware
     * @param class-string<RouteDispatchedMiddlewareContract>[] $routeDispatchedMiddleware
     * @param class-string<ThrowableCaughtMiddlewareContract>[] $throwableCaughtMiddleware
     * @param class-string<SendingResponseMiddlewareContract>[] $sendingResponseMiddleware
     * @param class-string<ResponseSentMiddlewareContract>[]    $responseSentMiddleware
     */
    public function __construct(
        public readonly string $namespace = 'App',
        public readonly string $dir = __DIR__,
        public readonly string $version = '1.0.0',
        public readonly string $environment = 'testing',
        public readonly bool $debugMode = false,
        public readonly string $timezone = 'UTC',
        public readonly string $key = 'some_secret_app_key',
        public readonly string $dataPath = 'App/Provider/Data',
        public readonly string $dataNamespace = 'App\\Provider\\Data',
        public readonly int $port = 50051,
        public readonly int $maxInboundMessages = GrpcConfigContract::DEFAULT_MAX_INBOUND_MESSAGES,
        public readonly array $providers = [],
        public readonly array $callbacks = [],
        public readonly array $callReceivedMiddleware = [],
        public readonly array $routeMatchedMiddleware = [],
        public readonly array $routeNotMatchedMiddleware = [],
        public readonly array $routeDispatchedMiddleware = [],
        public readonly array $throwableCaughtMiddleware = [],
        public readonly array $sendingResponseMiddleware = [],
        public readonly array $responseSentMiddleware = [],
    ) {
    }
}
