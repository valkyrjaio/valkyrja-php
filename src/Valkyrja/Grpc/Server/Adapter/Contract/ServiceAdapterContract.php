<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Server\Adapter\Contract;

use Valkyrja\Grpc\Server\Handler\Contract\ServiceHandlerContract;

interface ServiceAdapterContract
{
    /**
     * Begin accepting calls, dispatching each to the given handler.
     *
     * @param ServiceHandlerContract $handler The kernel entry point
     */
    public function start(ServiceHandlerContract $handler): void;

    /**
     * Gracefully stop accepting calls and shut down.
     */
    public function stop(): void;
}
