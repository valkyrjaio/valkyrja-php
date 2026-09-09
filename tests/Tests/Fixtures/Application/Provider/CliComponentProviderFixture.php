<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;

final class CliComponentProviderFixture implements ComponentProviderContract
{
    public static bool $publishedContainerData = false;

    public static function publish(ApplicationContract $app): void
    {
        if ($app->getDebugMode()) {
            return;
        }

        self::$publishedContainerData = true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getComponentProviders(ApplicationContract $app): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getContainerProviders(ApplicationContract $app): array
    {
        return [
            new CliContainerDataProviderFixture(),
            new CliRoutingDataProviderFixture(),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEventProviders(ApplicationContract $app): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCliProviders(ApplicationContract $app): array
    {
        return [
            new CliRouteProviderFixture(),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHttpProviders(ApplicationContract $app): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getGrpcProviders(ApplicationContract $app): array
    {
        return [];
    }
}
