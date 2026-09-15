<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Routing\Handler;

use Valkyrja\Application\Directory\Directory;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

use function date_default_timezone_get;

/**
 * Records the base path and the timezone that a running job sees.
 */
final class ProcessStateFixture
{
    public static string|null $basePath = null;

    public static string|null $timezone = null;

    public static function reset(): void
    {
        self::$basePath = null;
        self::$timezone = null;
    }

    public static function record(ContainerContract $container, RouteContract $route): JobResult
    {
        self::$basePath = Directory::$basePath;
        self::$timezone = date_default_timezone_get();

        return JobResult::ACK;
    }
}
