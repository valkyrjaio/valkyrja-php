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
use Valkyrja\Http\Routing\Attribute\Parameter;
use Valkyrja\Http\Routing\Attribute\Route\Handler;
use Valkyrja\Http\Routing\Constant\Regex;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;

/**
 * Exercises a matrix of attribute-defined dynamic routes against direct construction.
 */
final class RoutingCombinationsControllerFixture
{
    /** @var non-empty-string */
    public const string NUM_PATH   = '/num/{id}';
    /** @var non-empty-string */
    public const string NUM_NAME   = 'combinations.num';
    /** @var non-empty-string */
    public const string NUM_REGEX  = '/^\/num\/(?<id>\d+)$/';

    /** @var non-empty-string */
    public const string SLUG_PATH  = '/slug/{slug}';
    /** @var non-empty-string */
    public const string SLUG_NAME  = 'combinations.slug';
    /** @var non-empty-string */
    public const string SLUG_REGEX = '/^\/slug\/(?<slug>[a-zA-Z0-9-]+)$/';

    /** @var non-empty-string */
    public const string OPTIONAL_PATH  = '/optional/{opt?}';
    /** @var non-empty-string */
    public const string OPTIONAL_NAME  = 'combinations.optional';
    /** @var non-empty-string */
    public const string OPTIONAL_REGEX = '/^\/optional(?:\/)?(?<opt>[a-zA-Z]+)?$/';

    /** @var non-empty-string */
    public const string NON_CAPTURE_PATH  = '/nc/{nc}';
    /** @var non-empty-string */
    public const string NON_CAPTURE_NAME  = 'combinations.non-capture';
    /** @var non-empty-string */
    public const string NON_CAPTURE_REGEX = '/^\/nc\/(?:[a-zA-Z]+)$/';

    /** @var non-empty-string */
    public const string MULTI_PATH  = '/multi/{x}/{y}';
    /** @var non-empty-string */
    public const string MULTI_NAME  = 'combinations.multi';
    /** @var non-empty-string */
    public const string MULTI_REGEX = '/^\/multi\/(?<x>\d+)\/(?<y>[a-zA-Z]+)$/';

    public static function handler(ContainerContract $container, RouteContract $route): ResponseContract
    {
        return Response::create('handled');
    }

    #[DynamicRoute(
        path: self::NUM_PATH,
        name: self::NUM_NAME,
        parameters: [
            new Parameter(name: 'id', regex: Regex::NUM),
        ]
    )]
    #[Handler([self::class, 'handler'])]
    public function num(): ResponseContract
    {
        return Response::create('num');
    }

    #[DynamicRoute(
        path: self::SLUG_PATH,
        name: self::SLUG_NAME,
        parameters: [
            new Parameter(name: 'slug', regex: Regex::SLUG),
        ]
    )]
    #[Handler([self::class, 'handler'])]
    public function slug(): ResponseContract
    {
        return Response::create('slug');
    }

    #[DynamicRoute(
        path: self::OPTIONAL_PATH,
        name: self::OPTIONAL_NAME,
        parameters: [
            new Parameter(name: 'opt', regex: Regex::ALPHA, isOptional: true),
        ]
    )]
    #[Handler([self::class, 'handler'])]
    public function optional(): ResponseContract
    {
        return Response::create('optional');
    }

    #[DynamicRoute(
        path: self::NON_CAPTURE_PATH,
        name: self::NON_CAPTURE_NAME,
        parameters: [
            new Parameter(name: 'nc', regex: Regex::ALPHA, shouldCapture: false),
        ]
    )]
    #[Handler([self::class, 'handler'])]
    public function nonCapture(): ResponseContract
    {
        return Response::create('nonCapture');
    }

    #[DynamicRoute(
        path: self::MULTI_PATH,
        name: self::MULTI_NAME,
        parameters: [
            new Parameter(name: 'x', regex: Regex::NUM),
            new Parameter(name: 'y', regex: Regex::ALPHA),
        ]
    )]
    #[Handler([self::class, 'handler'])]
    public function multi(): ResponseContract
    {
        return Response::create('multi');
    }
}
