<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Broadcast\Provider;

use Override;
use Pusher\Pusher;
use Pusher\PusherException;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Broadcast\Broadcaster\Contract\BroadcasterContract;
use Valkyrja\Broadcast\Broadcaster\CryptPusherBroadcaster;
use Valkyrja\Broadcast\Broadcaster\LogBroadcaster;
use Valkyrja\Broadcast\Broadcaster\NullBroadcaster;
use Valkyrja\Broadcast\Broadcaster\PusherBroadcaster;
use Valkyrja\Broadcast\Data\BroadcastConfig;
use Valkyrja\Broadcast\Data\BroadcastLogConfig;
use Valkyrja\Broadcast\Data\BroadcastPusherConfig;
use Valkyrja\Broadcast\Data\Contract\BroadcastConfigContract;
use Valkyrja\Broadcast\Data\Contract\BroadcastLogConfigContract;
use Valkyrja\Broadcast\Data\Contract\BroadcastPusherConfigContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Crypt\Manager\Contract\CryptContract;

use const CURL_IPRESOLVE_V4;
use const CURLOPT_IPRESOLVE;

class BroadcastServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the broadcast config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof BroadcastConfigContract) {
            $container->setSingleton(BroadcastConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            BroadcastConfigContract::class,
            new BroadcastConfig()
        );
    }

    /**
     * Publish the pusher broadcaster config service.
     */
    public static function publishPusherConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof BroadcastPusherConfigContract) {
            $container->setSingleton(BroadcastPusherConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            BroadcastPusherConfigContract::class,
            new BroadcastPusherConfig()
        );
    }

    /**
     * Publish the log broadcaster config service.
     */
    public static function publishLogConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof BroadcastLogConfigContract) {
            $container->setSingleton(BroadcastLogConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            BroadcastLogConfigContract::class,
            new BroadcastLogConfig()
        );
    }

    /**
     * Publish the broadcaster service.
     */
    public static function publishBroadcaster(ContainerContract $container): void
    {
        $config = $container->getSingleton(BroadcastConfigContract::class);

        $container->setSingleton(
            BroadcasterContract::class,
            $container->getSingleton($config->defaultBroadcaster),
        );
    }

    /**
     * Publish the pusher broadcaster service.
     */
    public static function publishPusherBroadcaster(ContainerContract $container): void
    {
        $container->setSingleton(
            PusherBroadcaster::class,
            new PusherBroadcaster(
                $container->getSingleton(Pusher::class),
            )
        );
    }

    /**
     * Publish the crypt pusher broadcaster service.
     */
    public static function publishCryptPusherBroadcaster(ContainerContract $container): void
    {
        $container->setSingleton(
            CryptPusherBroadcaster::class,
            new CryptPusherBroadcaster(
                $container->getSingleton(Pusher::class),
                $container->getSingleton(CryptContract::class),
            )
        );
    }

    /**
     * Publish the pusher service.
     *
     * @throws PusherException
     */
    public static function publishPusher(ContainerContract $container): void
    {
        $config = $container->getSingleton(BroadcastPusherConfigContract::class);

        $container->setSingleton(
            Pusher::class,
            new Pusher(
                $config->pusherKey,
                $config->pusherSecret,
                $config->pusherId,
                [
                    'cluster'      => $config->pusherCluster,
                    'useTLS'       => $config->pusherUseTls,
                    'curl_options' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ]
            )
        );
    }

    /**
     * Publish the log broadcaster service.
     */
    public static function publishLogBroadcaster(ContainerContract $container): void
    {
        $config = $container->getSingleton(BroadcastLogConfigContract::class);

        $container->setSingleton(
            LogBroadcaster::class,
            new LogBroadcaster(
                $container->getSingleton($config->broadcastLogLogger),
            )
        );
    }

    /**
     * Publish the null broadcaster service.
     */
    public static function publishNullBroadcaster(ContainerContract $container): void
    {
        $container->setSingleton(
            NullBroadcaster::class,
            new NullBroadcaster()
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            BroadcastConfigContract::class       => [self::class, 'publishConfig'],
            BroadcastPusherConfigContract::class => [self::class, 'publishPusherConfig'],
            BroadcastLogConfigContract::class    => [self::class, 'publishLogConfig'],
            BroadcasterContract::class           => [self::class, 'publishBroadcaster'],
            PusherBroadcaster::class             => [self::class, 'publishPusherBroadcaster'],
            CryptPusherBroadcaster::class        => [self::class, 'publishCryptPusherBroadcaster'],
            Pusher::class                        => [self::class, 'publishPusher'],
            LogBroadcaster::class                => [self::class, 'publishLogBroadcaster'],
            NullBroadcaster::class               => [self::class, 'publishNullBroadcaster'],
        ];
    }
}
