<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Routing\Controller;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Attribute\Route;
use Valkyrja\Queue\Routing\Attribute\Route\Handler;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

/**
 * A controller that carries no class-level name.
 */
final class UnnamedJobControllerFixture
{
    public static function handle(ContainerContract $container, RouteContract $route): JobResult
    {
        return JobResult::ACK;
    }

    #[Route(name: 'RebuildIndex', description: 'Rebuild the index')]
    #[Handler([self::class, 'handle'])]
    public function rebuildIndex(): JobResult
    {
        return JobResult::ACK;
    }
}
