<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Data;

use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\ApplicationComponentProvider;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;

class Config implements ConfigContract
{
    /**
     * @param non-empty-string                          $namespace
     * @param non-empty-string                          $dir
     * @param non-empty-string                          $version
     * @param non-empty-string                          $environment
     * @param non-empty-string                          $timezone
     * @param non-empty-string                          $key
     * @param non-empty-string                          $dataPath
     * @param non-empty-string                          $dataNamespace
     * @param non-empty-string                          $applicationName
     * @param ComponentProviderContract[]               $providers
     * @param array<callable(ApplicationContract):void> $callbacks
     */
    public function __construct(
        public readonly string $namespace = 'App',
        public readonly string $dir = __DIR__,
        public readonly string $version = ApplicationInfo::VERSION,
        public readonly string $environment = 'production',
        public readonly bool $debugMode = false,
        public readonly string $timezone = 'UTC',
        public readonly string $key = 'some_secret_app_key',
        public readonly string $dataPath = 'App/Provider/Data',
        public readonly string $dataNamespace = 'App\\Provider\\Data',
        public readonly string $applicationName = 'valkyrja',
        public readonly array $providers = [
            new ApplicationComponentProvider(),
        ],
        public readonly array $callbacks = [],
    ) {
    }
}
