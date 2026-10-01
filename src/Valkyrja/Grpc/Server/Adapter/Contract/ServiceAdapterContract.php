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
     * The adapter also keeps the handler off the path that delivers the transport's callbacks,
     * because that path carries the resume signal the paused drain waits for. A handler that
     * occupies the path blocks its own resume, and the call deadlocks.
     *
     * @param ServiceHandlerContract $handler The kernel entry point
     */
    public function start(ServiceHandlerContract $handler): void;

    /**
     * Gracefully stop accepting calls and shut down.
     */
    public function stop(): void;
}
