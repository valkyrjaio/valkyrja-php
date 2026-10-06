<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Provider;

use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultLogMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultLogMiddlewareFixture;

/**
 * Binds the middleware fixtures that a fixture queue application schedules.
 */
final class QueueTestServiceProviderFixture implements ServiceProviderContract
{
    /**
     * Publish the result log middleware.
     */
    public static function publishResultLogMiddleware(ContainerContract $container): void
    {
        $container->setSingleton(ResultLogMiddlewareFixture::class, new ResultLogMiddlewareFixture());
    }

    /**
     * Publish the settling result log middleware.
     */
    public static function publishSettlingResultLogMiddleware(ContainerContract $container): void
    {
        $container->setSingleton(
            SettlingResultLogMiddlewareFixture::class,
            new SettlingResultLogMiddlewareFixture()
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            ResultLogMiddlewareFixture::class         => [self::class, 'publishResultLogMiddleware'],
            SettlingResultLogMiddlewareFixture::class => [self::class, 'publishSettlingResultLogMiddleware'],
        ];
    }
}
