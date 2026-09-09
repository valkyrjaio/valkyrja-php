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
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Application\Provider\QueueApplicationComponentProvider;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Middleware\Contract\JobReceivedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;

class QueueConfig implements QueueConfigContract
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
     * @param positive-int                                      $defaultMaxAttempts
     * @param int<0, max>                                       $defaultRetryDelayMs
     * @param ComponentProviderContract[]                       $providers
     * @param array<callable(ApplicationContract):void>         $callbacks
     * @param class-string<JobReceivedMiddlewareContract>[]     $jobReceivedMiddleware
     * @param class-string<RouteMatchedMiddlewareContract>[]    $routeMatchedMiddleware
     * @param class-string<RouteNotMatchedMiddlewareContract>[] $routeNotMatchedMiddleware
     * @param class-string<RouteDispatchedMiddlewareContract>[] $routeDispatchedMiddleware
     * @param class-string<ThrowableCaughtMiddlewareContract>[] $throwableCaughtMiddleware
     * @param class-string<SettlingResultMiddlewareContract>[]  $settlingResultMiddleware
     * @param class-string<ResultSettledMiddlewareContract>[]   $resultSettledMiddleware
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
        public readonly int $defaultMaxAttempts = Job::DEFAULT_MAX_ATTEMPTS,
        public readonly int $defaultRetryDelayMs = Job::DEFAULT_RETRY_DELAY_MS,
        public readonly bool $defaultRetryDelayMultiplyByAttempt = false,
        public readonly array $providers = [
            new QueueApplicationComponentProvider(),
        ],
        public readonly array $callbacks = [],
        public readonly array $jobReceivedMiddleware = [],
        public readonly array $routeMatchedMiddleware = [],
        public readonly array $routeNotMatchedMiddleware = [],
        public readonly array $routeDispatchedMiddleware = [],
        public readonly array $throwableCaughtMiddleware = [],
        public readonly array $settlingResultMiddleware = [],
        public readonly array $resultSettledMiddleware = [],
    ) {
    }
}
