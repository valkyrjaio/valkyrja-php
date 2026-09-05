<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Provider;

use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

class QueueMessageServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the job factory service.
     *
     * @param ContainerContract $container The container
     */
    public static function publishJobFactory(ContainerContract $container): void
    {
        $container->setSingleton(
            JobFactoryContract::class,
            new JobFactory()
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            JobFactoryContract::class => [self::class, 'publishJobFactory'],
        ];
    }
}
