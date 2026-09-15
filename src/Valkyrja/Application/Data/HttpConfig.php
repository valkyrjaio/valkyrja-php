<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Data;

use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Data\Contract\HttpConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Application\Provider\HttpApplicationComponentProvider;
use Valkyrja\Http\Middleware\Contract\RequestReceivedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Http\Server\Middleware\RouteNotMatched\ViewRouteNotMatchedMiddleware;
use Valkyrja\Http\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware;
use Valkyrja\Http\Server\Middleware\ThrowableCaught\ViewThrowableCaughtMiddleware;

class HttpConfig implements HttpConfigContract
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
     * @param non-empty-string                                  $applicationName
     * @param ComponentProviderContract[]                       $providers
     * @param array<callable(ApplicationContract):void>         $callbacks
     * @param class-string<RequestReceivedMiddlewareContract>[] $requestReceivedMiddleware
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
        public readonly string $version = ApplicationInfo::VERSION,
        public readonly string $environment = 'production',
        public readonly bool $debugMode = false,
        public readonly string $timezone = 'UTC',
        public readonly string $key = 'some_secret_app_key',
        public readonly string $dataPath = 'App/Provider/Data',
        public readonly string $dataNamespace = 'App\\Provider\\Data',
        public readonly string $applicationName = 'valkyrja',
        public readonly array $providers = [
            new HttpApplicationComponentProvider(),
        ],
        public readonly array $callbacks = [],
        public readonly array $requestReceivedMiddleware = [],
        public readonly array $routeMatchedMiddleware = [],
        public readonly array $routeNotMatchedMiddleware = [
            ViewRouteNotMatchedMiddleware::class,
        ],
        public readonly array $routeDispatchedMiddleware = [],
        public readonly array $throwableCaughtMiddleware = [
            LogThrowableCaughtMiddleware::class,
            ViewThrowableCaughtMiddleware::class,
        ],
        public readonly array $sendingResponseMiddleware = [],
        public readonly array $responseSentMiddleware = [],
    ) {
    }
}
