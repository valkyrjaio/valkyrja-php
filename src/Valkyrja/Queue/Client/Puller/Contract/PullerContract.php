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
