<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Server\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\CallReceivedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Grpc\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Grpc\Server\Handler\Contract\ServiceHandlerContract;
use Valkyrja\Grpc\Server\Handler\ServiceHandler;

class GrpcServerServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the ServiceHandler service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishServiceHandler(ContainerContract $container): void
    {
        $app = $container->getSingleton(ApplicationContract::class);

        $container->setSingleton(
            ServiceHandlerContract::class,
            new ServiceHandler(
                container: $container,
                router: $container->getSingleton(RouterContract::class),
                callReceivedHandler: $container->getSingleton(CallReceivedHandlerContract::class),
                throwableCaughtHandler: $container->getSingleton(ThrowableCaughtHandlerContract::class),
                sendingResponseHandler: $container->getSingleton(SendingResponseHandlerContract::class),
                responseSentHandler: $container->getSingleton(ResponseSentHandlerContract::class),
                debug: $app->getDebugMode(),
            )
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            ServiceHandlerContract::class => [self::class, 'publishServiceHandler'],
        ];
    }
}
