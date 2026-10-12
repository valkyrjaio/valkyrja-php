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
use Valkyrja\Http\Routing\Attribute\DynamicRoute;
use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Attribute\Route\Middleware;
use Valkyrja\Http\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Data\Parameter;
use Valkyrja\Tests\Fixtures\Http\Middleware\AllMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Struct\IndexedJsonRequestStructEnum;
use Valkyrja\Tests\Fixtures\Http\Struct\ResponseStructEnum;

/**
 * Controller class to test routes.
 */
final class ControllerWithAllMiddlewareFixture
{
    /** @var non-empty-string */
    public const string WELCOME_PATH = '/';
    /** @var non-empty-string */
    public const string WELCOME_NAME = 'welcome';

    /** @var non-empty-string */
    public const string DYNAMIC_PATH = '/{dynamic}';
    /** @var non-empty-string */
    public const string DYNAMIC_NAME = 'dynamic';

    public static function handler(ContainerContract $container, RouteContract $route): ResponseContract
    {
        return Response::create('handled');
    }

    #[Route(
        path: self::WELCOME_PATH,
        name: self::WELCOME_NAME,
        requestStruct: IndexedJsonRequestStructEnum::first,
        responseStruct: ResponseStructEnum::first,
    )]
    #[RouteHandler([self::class, 'handler'])]
    #[Middleware(AllMiddlewareFixture::class)]
    public function welcome(): ResponseContract
    {
        return Response::create('welcome');
    }

    #[DynamicRoute(
        path: self::DYNAMIC_PATH,
        name: self::DYNAMIC_NAME,
        parameters: [new Parameter('dynamic', regex: '/\d+/')],
        requestStruct: IndexedJsonRequestStructEnum::first,
        responseStruct: ResponseStructEnum::first,
    )]
    #[RouteHandler([self::class, 'handler'])]
    #[Middleware(AllMiddlewareFixture::class)]
    public function welcomeDynamic(int $dynamic): ResponseContract
    {
        return Response::create("dynamic$dynamic");
    }
}
