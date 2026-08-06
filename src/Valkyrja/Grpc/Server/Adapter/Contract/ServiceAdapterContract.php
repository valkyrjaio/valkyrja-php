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

/**
 * Bridges an external gRPC server implementation (RoadRunner, OpenSwoole, grpc-java, …) to the
 * framework's ServiceHandler.
 *
 * This interface is part of the worker-agnostic surface — portable across every language port —
 * even though implementations are per-worker. An adapter accepts native calls, builds a
 * ServiceCall, hands it to the ServiceHandler, and translates the returned ServiceResponse back to
 * the library's native response API. Adapter-specific configuration (TLS, worker pools, port
 * binding) lives on the implementation, not here.
 *
 * Flow control is asymmetric, and the two directions are the adapter's to reconcile:
 *
 * - Inbound is bounded by the config's `maxInboundMessages` — a hard cap under the buffered model,
 *   a high-water mark for back-pressure under the streaming model.
 * - Outbound is bounded by nothing the framework supplies. The drain is pull-based end to end, so
 *   an adapter must ask its transport whether it can accept another message and pause between
 *   messages when it cannot; a peer that is alive but not reading would otherwise grow the write
 *   queue for the life of the call. Do not reuse the inbound cap for this — it bounds one
 *   direction only.
 *
 * Pausing is not cancelling. Cancellation *ends* the drain — `ServiceCall::cancellable()` stops
 * yielding and the call closes. Back-pressure *suspends* it and resumes once the transport drains.
 * Conflating them either drops messages for a slow client or hangs on a cancelled one, and both
 * fail silently.
 */
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
