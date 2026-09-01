<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Kernel\Contract;

use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Cli\Routing\Provider\Contract\CliRouteProviderContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Event\Provider\Contract\ListenerProviderContract;
use Valkyrja\Grpc\Routing\Provider\Contract\GrpcRouteProviderContract;
use Valkyrja\Http\Routing\Provider\Contract\HttpRouteProviderContract;

interface ApplicationContract
{
    /**
     * Get the container.
     */
    public function getContainer(): ContainerContract;

    /**
     * Publish the component provider callbacks.
     */
    public function publishProviderCallbacks(): void;

    /**
     * Get the registered component providers.
     *
     * @return ComponentProviderContract[]
     */
    public function getProviders(): array;

    /**
     * Get all the registered components' container service providers.
     *
     * @return ServiceProviderContract[]
     */
    public function getContainerProviders(): array;

    /**
     * Get all the registered components' event providers.
     *
     * @return ListenerProviderContract[]
     */
    public function getEventProviders(): array;

    /**
     * Get all the registered components' cli providers.
     *
     * @return CliRouteProviderContract[]
     */
    public function getCliProviders(): array;

    /**
     * Get all the registered components' http providers.
     *
     * @return HttpRouteProviderContract[]
     */
    public function getHttpProviders(): array;

    /**
     * Get all the registered components' gRPC providers.
     *
     * @return GrpcRouteProviderContract[]
     */
    public function getGrpcProviders(): array;

    /**
     * Whether the application is running in debug mode or not.
     */
    public function getDebugMode(): bool;

    /**
     * Get the environment with which the application is running in.
     */
    public function getEnvironment(): string;

    /**
     * Get the application version.
     */
    public function getVersion(): string;
}
