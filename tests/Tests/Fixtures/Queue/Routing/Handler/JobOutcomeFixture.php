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

use RuntimeException;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

/**
 * Handlers whose outcome a test dictates.
 */
final class JobOutcomeFixture
{
    /**
     * A handler that always throws, to drive the throwable-caught path.
     */
    public static function throws(ContainerContract $container, RouteContract $route): JobResult
    {
        throw new RuntimeException('the job blew up');
    }
}
