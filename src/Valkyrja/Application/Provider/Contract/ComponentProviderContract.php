<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Provider\Contract;

use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Cli\Routing\Provider\Contract\CliRouteProviderContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Event\Provider\Contract\ListenerProviderContract;
use Valkyrja\Http\Routing\Provider\Contract\HttpRouteProviderContract;
use Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract;

interface ComponentProviderContract
{
    /**
     * Get the component providers this component depends on.
     *
     * @return ComponentProviderContract[]
     */
    public function getComponentProviders(ApplicationContract $app): array;

    /**
     * Get the component's container service providers.
     *
     * @return ServiceProviderContract[]
     */
    public function getContainerProviders(ApplicationContract $app): array;

    /**
     * Get the component's event listener providers.
     *
     * @return ListenerProviderContract[]
     */
    public function getEventProviders(ApplicationContract $app): array;

    /**
     * Get the component's cli route providers.
     *
     * @return CliRouteProviderContract[]
     */
    public function getCliProviders(ApplicationContract $app): array;

    /**
     * Get the component's http route providers.
     *
     * @return HttpRouteProviderContract[]
     */
    public function getHttpProviders(ApplicationContract $app): array;

    /**
     * Get the component's queue route providers.
     *
     * @return QueueRouteProviderContract[]
     */
    public function getQueueProviders(ApplicationContract $app): array;
}
