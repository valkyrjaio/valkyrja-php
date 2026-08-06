<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Puller\Contract;

use Valkyrja\Queue\Message\Job\Contract\JobContract;

/**
 * The pull-side mapper: the connect-and-poll client.
 *
 * Pull means the framework polls the processor — an SQS long-poll, an AMQP
 * consumer, a Redis blocking pop, a database poll. That is just a loop, so the
 * framework never ships a server for it; this contract is only the piece that
 * knows how to talk to one processor, and the entry owns the loop around it.
 */
interface PullerContract
{
    /**
     * Connect to the processor.
     */
    public function connect(): void;

    /**
     * Wait for the next job, or return null when nothing arrived in time.
     */
    public function receive(): JobContract|null;

    /**
     * Disconnect from the processor.
     */
    public function disconnect(): void;
}
