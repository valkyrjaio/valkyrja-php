<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\JobReceivedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\SettlingResultHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;
use Valkyrja\Queue\Server\Handler\JobHandler;
use Valkyrja\Queue\Server\Mapper\Contract\RequestMapperContract;
use Valkyrja\Queue\Server\Mapper\RequestMapper;
use Valkyrja\Queue\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware;
use Valkyrja\Queue\Server\Middleware\ThrowableCaught\RetryPolicyThrowableCaughtMiddleware;

class QueueServerServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the job handler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishJobHandler(ContainerContract $container): void
    {
        $app = $container->getSingleton(ApplicationContract::class);

        $container->setSingleton(
            JobHandlerContract::class,
            new JobHandler(
                container: $container,
                router: $container->getSingleton(RouterContract::class),
                jobReceivedHandler: $container->getSingleton(JobReceivedHandlerContract::class),
                throwableCaughtHandler: $container->getSingleton(ThrowableCaughtHandlerContract::class),
                settlingResultHandler: $container->getSingleton(SettlingResultHandlerContract::class),
                resultSettledHandler: $container->getSingleton(ResultSettledHandlerContract::class),
                debug: $app->getDebugMode(),
            )
        );
    }

    /**
     * Publish the log throwable caught middleware.
     *
     * @param ContainerContract $container The container
     */
    public static function publishLogThrowableCaughtMiddleware(ContainerContract $container): void
    {
        $container->setSingleton(
            LogThrowableCaughtMiddleware::class,
            new LogThrowableCaughtMiddleware(
                logger: $container->getSingleton(LoggerContract::class),
            )
        );
    }

    /**
     * Publish the retry policy throwable caught middleware.
     *
     * @param ContainerContract $container The container
     */
    public static function publishRetryPolicyThrowableCaughtMiddleware(ContainerContract $container): void
    {
        $container->setSingleton(
            RetryPolicyThrowableCaughtMiddleware::class,
            new RetryPolicyThrowableCaughtMiddleware()
        );
    }

    /**
     * Publish the request mapper service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishRequestMapper(ContainerContract $container): void
    {
        $container->setSingleton(
            RequestMapperContract::class,
            new RequestMapper()
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            JobHandlerContract::class                   => [self::class, 'publishJobHandler'],
            RequestMapperContract::class                => [self::class, 'publishRequestMapper'],
            LogThrowableCaughtMiddleware::class         => [self::class, 'publishLogThrowableCaughtMiddleware'],
            RetryPolicyThrowableCaughtMiddleware::class => [self::class, 'publishRetryPolicyThrowableCaughtMiddleware'],
        ];
    }
}
