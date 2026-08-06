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

/**
 * Opts a host application into running jobs in-process.
 *
 * An Http, Cli, or gRPC config may implement this; present means that host can
 * run jobs against the returned queue config, absent means no embedding — use
 * an external processor or a dedicated worker app.
 *
 * Because it is a config-level choice it is naturally per-environment: a dev
 * config wires it and runs jobs synchronously with no broker infrastructure,
 * while a production config omits it and points at an external processor. Same
 * job code, environment swapped by config alone.
 *
 * The base Http, Cli, and gRPC configs have zero knowledge of Queue — this is
 * opt-in coupling only, which is what keeps the modules independent by default.
 */
interface QueueConfigProvidedContract
{
    /**
     * Get the queue configuration this host embeds.
     */
    public function getQueueConfig(): QueueConfigContract;
}
