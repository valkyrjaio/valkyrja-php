<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Http\Routing\Controller;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Http\Message\Response\Response;
use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;

/** A controller whose method repeats the route attribute, where only one route names a handler. */
final class ControllerPartialHandlerFixture
{
    /** @var non-empty-string */
    public const string WIRED_NAME = 'wired';
    /** @var non-empty-string */
    public const string BARE_NAME = 'bare';

    public static function handler(ContainerContract $container, RouteContract $route): ResponseContract
    {
        return Response::create('handled');
    }

    #[Route(path: '/wired', name: self::WIRED_NAME, handler: [self::class, 'handler'])]
    #[Route(path: '/bare', name: self::BARE_NAME)]
    public function repeated(): ResponseContract
    {
        return Response::create('repeated');
    }
}
