<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Kernel;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;

class ChildApplication implements ApplicationContract
{
    public function __construct(
        protected ApplicationContract $parent,
        protected ContainerContract $container,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getContainer(): ContainerContract
    {
        return $this->container;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishProviderCallbacks(): void
    {
        $this->parent->publishProviderCallbacks();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getProviders(): array
    {
        return $this->parent->getProviders();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getContainerProviders(): array
    {
        return $this->parent->getContainerProviders();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEventProviders(): array
    {
        return $this->parent->getEventProviders();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCliProviders(): array
    {
        return $this->parent->getCliProviders();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHttpProviders(): array
    {
        return $this->parent->getHttpProviders();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getGrpcProviders(): array
    {
        return $this->parent->getGrpcProviders();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getDebugMode(): bool
    {
        return $this->parent->getDebugMode();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEnvironment(): string
    {
        return $this->parent->getEnvironment();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getVersion(): string
    {
        return $this->parent->getVersion();
    }
}
