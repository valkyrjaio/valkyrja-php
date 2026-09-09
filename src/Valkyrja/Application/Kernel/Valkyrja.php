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
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Cli\Routing\Provider\Contract\CliRouteProviderContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Event\Provider\Contract\ListenerProviderContract;
use Valkyrja\Grpc\Routing\Provider\Contract\GrpcRouteProviderContract;
use Valkyrja\Http\Routing\Provider\Contract\HttpRouteProviderContract;

use function array_merge;
use function date_default_timezone_set;

class Valkyrja implements ApplicationContract
{
    /** @var ComponentProviderContract[] */
    protected array $providers = [];
    /** @var ServiceProviderContract[] */
    protected array $serviceProviders = [];
    /** @var ListenerProviderContract[] */
    protected array $eventProviders = [];
    /** @var CliRouteProviderContract[] */
    protected array $cliRouteProviders = [];
    /** @var HttpRouteProviderContract[] */
    protected array $httpRouteProviders = [];
    /** @var GrpcRouteProviderContract[] */
    protected array $grpcRouteProviders = [];

    public function __construct(
        protected ContainerContract $container,
        protected ConfigContract $config,
    ) {
        $this->bootstrapTimezone();
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
        foreach ($this->config->callbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getProviders(): array
    {
        if ($this->providers !== []) {
            return $this->providers;
        }

        foreach ($this->config->providers as $provider) {
            $this->collectProviders($provider);
        }

        return $this->providers;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getContainerProviders(): array
    {
        if ($this->serviceProviders !== []) {
            return $this->serviceProviders;
        }

        $providers = [];

        foreach ($this->getProviders() as $provider) {
            $providers[] = $provider->getContainerProviders($this);
        }

        $this->serviceProviders = $providers !== []
            ? array_merge(...$providers)
            : [];

        return $this->serviceProviders;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEventProviders(): array
    {
        if ($this->eventProviders !== []) {
            return $this->eventProviders;
        }

        $providers = [];

        foreach ($this->getProviders() as $provider) {
            $providers[] = $provider->getEventProviders($this);
        }

        $this->eventProviders = $providers !== []
            ? array_merge(...$providers)
            : [];

        return $this->eventProviders;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCliProviders(): array
    {
        if ($this->cliRouteProviders !== []) {
            return $this->cliRouteProviders;
        }

        $providers = [];

        foreach ($this->getProviders() as $provider) {
            $providers[] = $provider->getCliProviders($this);
        }

        $this->cliRouteProviders = $providers !== []
            ? array_merge(...$providers)
            : [];

        return $this->cliRouteProviders;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHttpProviders(): array
    {
        if ($this->httpRouteProviders !== []) {
            return $this->httpRouteProviders;
        }

        $providers = [];

        foreach ($this->getProviders() as $provider) {
            $providers[] = $provider->getHttpProviders($this);
        }

        $this->httpRouteProviders = $providers !== []
            ? array_merge(...$providers)
            : [];

        return $this->httpRouteProviders;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getGrpcProviders(): array
    {
        if ($this->grpcRouteProviders !== []) {
            return $this->grpcRouteProviders;
        }

        $providers = [];

        foreach ($this->getProviders() as $provider) {
            $providers[] = $provider->getGrpcProviders($this);
        }

        $this->grpcRouteProviders = $providers !== []
            ? array_merge(...$providers)
            : [];

        return $this->grpcRouteProviders;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getDebugMode(): bool
    {
        return $this->config->debugMode;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEnvironment(): string
    {
        return $this->config->environment;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getVersion(): string
    {
        return $this->config->version;
    }

    /**
     * Recursively expand a component provider and its sub-providers depth-first,
     * ensuring each sub-provider is added before the provider that depends on it.
     */
    protected function collectProviders(ComponentProviderContract $provider): void
    {
        foreach ($provider->getComponentProviders($this) as $subProvider) {
            $this->collectProviders($subProvider);
        }

        $this->providers[] = $provider;
    }

    /**
     * Bootstrap the timezone.
     */
    protected function bootstrapTimezone(): void
    {
        date_default_timezone_set($this->config->timezone);
    }
}
