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
use Valkyrja\Queue\Routing\Attribute\Route\Middleware;
use Valkyrja\Queue\Routing\Attribute\Route\Name;
use Valkyrja\Queue\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ResultSettledMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\SettlingResultMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Queue\Middleware\ThrowableCaughtMiddlewareFixture;

#[Name('acme')]
final class JobControllerFixture
{
    public static function handle(ContainerContract $container, RouteContract $route): JobResult
    {
        return JobResult::ACK;
    }

    #[Route(name: 'SendWelcomeEmail', description: 'Send the welcome email')]
    #[RouteHandler([self::class, 'handle'])]
    public function sendWelcomeEmail(): JobResult
    {
        return JobResult::ACK;
    }

    #[Route(name: 'ChargeCard', description: 'Charge the card')]
    #[Name('v2')]
    #[Middleware(RouteMatchedMiddlewareFixture::class)]
    #[Middleware(RouteDispatchedMiddlewareFixture::class)]
    #[Middleware(ThrowableCaughtMiddlewareFixture::class)]
    #[Middleware(SettlingResultMiddlewareFixture::class)]
    #[Middleware(ResultSettledMiddlewareFixture::class)]
    public function chargeCard(): JobResult
    {
        return JobResult::ACK;
    }
}
