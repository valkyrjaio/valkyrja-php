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
use Valkyrja\Tests\Fixtures\Event\Provider\ListenerProviderFixture;

/**
 * Returns sub-providers that existing test providers also return, so deduplication can be asserted.
 */
final class DuplicateSubProviderFixture implements ComponentProviderContract
{
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
            CliContainerDataProviderFixture::class,
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEventProviders(ApplicationContract $app): array
    {
        return [
            ListenerProviderFixture::class,
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCliProviders(ApplicationContract $app): array
    {
        return [
            CliRouteProviderFixture::class,
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHttpProviders(ApplicationContract $app): array
    {
        return [
            HttpRouteProviderFixture::class,
        ];
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
