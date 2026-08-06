<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Provider;

use Override;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Client\Requeuer\Requeuer;

class QueueClientServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the client service.
     *
     * The sync client is the zero-config default; an application swaps in a
     * deferred, in-memory, or broker-backed one by re-publishing this binding,
     * which is why swapping processors is a config change and not a code one.
     *
     * @param ContainerContract $container The container
     */
    public static function publishClient(ContainerContract $container): void
    {
        $config = $container->getSingleton(QueueConfigContract::class);

        /** @var non-empty-string $version */
        $version = $config->version;

        $container->setSingleton(
            ClientContract::class,
            new SyncClient(
                config: $config,
                version: $version,
            )
        );
    }

    /**
     * Publish the re-queuer service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishRequeuer(ContainerContract $container): void
    {
        $container->setSingleton(
            RequeuerContract::class,
            new Requeuer()
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            ClientContract::class   => [self::class, 'publishClient'],
            RequeuerContract::class => [self::class, 'publishRequeuer'],
        ];
    }
}
