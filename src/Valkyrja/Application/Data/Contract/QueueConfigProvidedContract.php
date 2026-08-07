<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Data\Contract;

interface QueueConfigProvidedContract
{
    /**
     * Get the queue configuration this host embeds.
     */
    public function getQueueConfig(): QueueConfigContract;
}
