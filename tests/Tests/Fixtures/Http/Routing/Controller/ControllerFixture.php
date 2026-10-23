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
use Valkyrja\Http\Message\Response\Factory\Contract\ResponseFactoryContract;
use Valkyrja\Http\Message\Response\Response;
use Valkyrja\Http\Routing\Attribute\DynamicRoute;
use Valkyrja\Http\Routing\Attribute\Parameter;
use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Attribute\Route\Handler;
use Valkyrja\Http\Routing\Attribute\Route\Middleware;
use Valkyrja\Http\Routing\Attribute\Route\RequestMethod\Get;
use Valkyrja\Http\Routing\Attribute\Route\RequestMethod\Head;
use Valkyrja\Http\Routing\Attribute\Route\RequestMethod\Post;
use Valkyrja\Http\Routing\Attribute\Route\RequestStruct;
use Valkyrja\Http\Routing\Attribute\Route\ResponseStruct;
use Valkyrja\Http\Routing\Constant\Regex;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Http\Middleware\ResponseSentMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\RouteDispatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\RouteMatchedMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\SendingResponseMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Middleware\ThrowableCaughtMiddlewareFixture;
use Valkyrja\Tests\Fixtures\Http\Routing\Provider\RouteProviderFixture;
use Valkyrja\Tests\Fixtures\Http\Struct\IndexedJsonRequestStructEnum;
use Valkyrja\Tests\Fixtures\Http\Struct\ResponseStructEnum;
use Valkyrja\Type\Contract\TypeContract;
use Valkyrja\Type\Data\Cast;

/**
 * Controller class to test routes.
 */
final class ControllerFixture
{
    /** @var non-empty-string */
    public const string WELCOME_PATH = '/';
    /** @var non-empty-string */
    public const string WELCOME_NAME = 'welcome';
    /** @var non-empty-string */
    public const string PARAMETERS_PATH = '/parameters/{name}';
    /** @var non-empty-string */
    public const string PARAMETERS_NAME = 'parameters';
    /** @var non-empty-string */
    public const string PARAMETERS_PARAMETER_NAME = 'name';
    /** @var non-empty-string */
    public const string DYNAMIC_PATH = '/dynamic/{foo}/{bar}';
    /** @var non-empty-string */
    public const string DYNAMIC_NAME = 'dynamic';
    /** @var non-empty-string */
    public const string DYNAMIC_PARAMETER_NAME = 'foo';
    /** @var non-empty-string */
    public const string DYNAMIC_PARAMETER_NAME2 = 'bar';

    public static function handler(ContainerContract $container, RouteContract $route): ResponseContract
    {
        return Response::create('handled');
    }

    #[DynamicRoute(
        path: self::DYNAMIC_PATH,
        name: self::DYNAMIC_NAME,
        parameters: [
            new Parameter(name: self::DYNAMIC_PARAMETER_NAME, regex: Regex::ALPHA, cast: new Cast(TypeContract::class)),
        ]
    )]
    #[Handler([self::class, 'handler'])]
    #[Middleware(RouteDispatchedMiddlewareFixture::class)]
    #[Middleware(RouteMatchedMiddlewareFixture::class)]
    #[Middleware(SendingResponseMiddlewareFixture::class)]
    #[Middleware(ResponseSentMiddlewareFixture::class)]
    #[Middleware(ThrowableCaughtMiddlewareFixture::class)]
    #[RequestStruct(IndexedJsonRequestStructEnum::first)]
    #[ResponseStruct(ResponseStructEnum::first)]
    public static function dynamic(
        ResponseFactoryContract $responseFactory,
        string $foo,
        #[Parameter(name: self::DYNAMIC_PARAMETER_NAME2, regex: Regex::ALPHA, cast: new Cast(TypeContract::class))]
        string $bar,
    ): ResponseContract {
        return $responseFactory->createResponse(
            content: "dynamic$foo$bar"
        );
    }

    #[Route(path: self::WELCOME_PATH, name: self::WELCOME_NAME)]
    #[Handler([self::class, 'handler'])]
    public function welcome(): ResponseContract
    {
        return Response::create('welcome');
    }

    #[Get]
    #[Head]
    #[Post]
    #[Route(path: self::PARAMETERS_PATH, name: self::PARAMETERS_NAME)]
    #[Handler([RouteProviderFixture::class, 'handler'])]
    #[Middleware(RouteDispatchedMiddlewareFixture::class)]
    #[Middleware(RouteMatchedMiddlewareFixture::class)]
    #[Middleware(SendingResponseMiddlewareFixture::class)]
    #[Middleware(ResponseSentMiddlewareFixture::class)]
    #[Middleware(ThrowableCaughtMiddlewareFixture::class)]
    #[RequestStruct(IndexedJsonRequestStructEnum::first)]
    #[ResponseStruct(ResponseStructEnum::first)]
    public function parameters(
        ResponseFactoryContract $responseFactory,
        #[Parameter(name: self::PARAMETERS_PARAMETER_NAME, regex: Regex::ALPHA, cast: new Cast(TypeContract::class))]
        string $name,
    ): ResponseContract {
        return $responseFactory->createResponse(
            content: "parameters$name"
        );
    }
}
