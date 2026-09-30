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
     * The adapter bounds both directions of each call. `maxInboundMessages` bounds the inbound
     * direction. Nothing in the framework bounds the outbound direction, so the adapter checks
     * whether the transport can accept a message before it writes one. When the transport cannot,
     * the adapter pauses the drain and resumes it later. A paused drain is not a cancelled call:
     * cancellation ends the drain, and backpressure only pauses it.
     *
     * @param ServiceHandlerContract $handler The kernel entry point
     */
    public function start(ServiceHandlerContract $handler): void;

    /**
     * Gracefully stop accepting calls and shut down.
     */
    public function stop(): void;
}
