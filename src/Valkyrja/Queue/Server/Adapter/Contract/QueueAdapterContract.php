<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Adapter\Contract;

use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;

/**
 * Bridges an external processor (SQS, AMQP, Redis, Cloud Tasks, …) to the
 * kernel.
 *
 * An adapter owns both ends of a delivery and nothing in between: it accepts a
 * native delivery and normalizes it into a job, then takes the outcome the
 * kernel returns and settles it back with the processor. Routing, middleware,
 * and the handler are all processor-agnostic — only the adapter knows what an
 * SQS receipt or a Cloud Tasks POST looks like.
 *
 * Processor-specific configuration (connection, prefetch, visibility, the
 * dead-letter destination) lives on the implementation, never here.
 */
interface QueueAdapterContract
{
    /**
     * Begin consuming, dispatching each delivery to the given handler.
     */
    public function start(JobHandlerContract $handler): void;

    /**
     * Gracefully stop consuming and drain anything in flight.
     */
    public function stop(): void;
}
