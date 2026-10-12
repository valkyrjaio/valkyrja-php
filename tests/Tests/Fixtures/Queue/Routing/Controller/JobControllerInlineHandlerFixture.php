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
use Valkyrja\Queue\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

/** A controller whose route names its handler on the route attribute itself. */
final class JobControllerInlineHandlerFixture
{
    public static function handle(ContainerContract $container, RouteContract $route): JobResult
    {
        return JobResult::ACK;
    }

    public static function viaAttribute(ContainerContract $container, RouteContract $route): JobResult
    {
        return JobResult::RETRY;
    }

    #[Route(name: 'Inline', description: 'Inline handler', handler: [self::class, 'handle'])]
    public function inline(): JobResult
    {
        return JobResult::ACK;
    }

    #[Route(name: 'Both', description: 'Both forms', handler: [self::class, 'handle'])]
    #[RouteHandler([self::class, 'viaAttribute'])]
    public function both(): JobResult
    {
        return JobResult::ACK;
    }
}
