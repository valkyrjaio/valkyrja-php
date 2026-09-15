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

use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;

interface ConfigContract
{
    /** @var non-empty-string */
    public string $namespace {
        get;
    }
    /** @var non-empty-string */
    public string $dir {
        get;
    }
    /** @var non-empty-string */
    public string $version {
        get;
    }
    /** @var non-empty-string */
    public string $environment {
        get;
    }
    public bool $debugMode {
        get;
    }
    /** @var non-empty-string */
    public string $timezone {
        get;
    }
    /** @var non-empty-string */
    public string $key {
        get;
    }
    /** @var non-empty-string */
    public string $dataPath {
        get;
    }
    /** @var non-empty-string */
    public string $dataNamespace {
        get;
    }
    /** @var non-empty-string */
    public string $applicationName {
        get;
    }
    /** @var ComponentProviderContract[] */
    public array $providers {
        get;
    }
    /** @var array<callable(ApplicationContract):void> */
    public array $callbacks {
        get;
    }
}
