<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Log\Data\Contract;

use Valkyrja\Log\Enum\LogLevel;

interface LogPsrConfigContract
{
    /**
     * The name to give the log channel and the log file.
     *
     * A null value tells the provider to name the channel for the application
     * and the current date. The date is only known at run time, so the config
     * cannot hold it as a default.
     *
     * @var non-empty-string|null
     */
    public string|null $psrName {
        get;
    }

    /**
     * The directory to write the log file to.
     *
     * A null value tells the provider to write to the framework logs storage
     * directory. The framework resolves that directory after it sets the base
     * path, so the config cannot hold it as a default.
     *
     * @var non-empty-string|null
     */
    public string|null $psrFilePath {
        get;
    }

    public LogLevel $psrLevel {
        get;
    }
}
