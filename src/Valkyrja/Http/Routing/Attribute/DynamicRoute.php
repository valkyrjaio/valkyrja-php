<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Attribute;

use Attribute;
use Valkyrja\Attribute\Contract\ReflectionAwareAttributeContract;
use Valkyrja\Attribute\Trait\ReflectionAwareAttribute;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Http\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Http\Routing\Data\Contract\ParameterContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Data\DynamicRoute as ParentRoute;
use Valkyrja\Http\Routing\Support\NoRouteHandler;
use Valkyrja\Http\Struct\Request\Contract\RequestStructContract;
use Valkyrja\Http\Struct\Response\Contract\ResponseStructContract;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DynamicRoute extends ParentRoute implements ReflectionAwareAttributeContract
{
    use ReflectionAwareAttribute;

    /**
     * @param non-empty-string                                                   $path                      The path
     * @param non-empty-string                                                   $name                      The name
     * @param RequestMethod[]                                                    $requestMethods            The request methods
     * @param ParameterContract[]                                                $parameters                The parameters
     * @param (callable(ContainerContract, RouteContract):ResponseContract)|null $handler                   The handler
     * @param class-string<RouteMatchedMiddlewareContract>[]                     $routeMatchedMiddleware    The route matched middleware
     * @param class-string<RouteDispatchedMiddlewareContract>[]                  $routeDispatchedMiddleware The route dispatched middleware
     * @param class-string<ThrowableCaughtMiddlewareContract>[]                  $throwableCaughtMiddleware The throwable caught middleware
     * @param class-string<SendingResponseMiddlewareContract>[]                  $sendingResponseMiddleware The sending response middleware
     * @param class-string<ResponseSentMiddlewareContract>[]                     $responseSentMiddleware    The response sent middleware
     */
    public function __construct(
        protected string $path,
        protected string $name,
        protected array $parameters,
        callable|null $handler = null,
        protected array $requestMethods = [RequestMethod::HEAD, RequestMethod::GET],
        protected array $routeMatchedMiddleware = [],
        protected array $routeDispatchedMiddleware = [],
        protected array $throwableCaughtMiddleware = [],
        protected array $sendingResponseMiddleware = [],
        protected array $responseSentMiddleware = [],
        protected RequestStructContract|null $requestStruct = null,
        protected ResponseStructContract|null $responseStruct = null,
    ) {
        parent::__construct(
            path: $path,
            name: $name,
            regex: '',
            parameters: $parameters,
            handler: $handler ?? new NoRouteHandler(),
            requestMethods: $requestMethods,
            routeMatchedMiddleware: $routeMatchedMiddleware,
            routeDispatchedMiddleware: $routeDispatchedMiddleware,
            throwableCaughtMiddleware: $throwableCaughtMiddleware,
            sendingResponseMiddleware: $sendingResponseMiddleware,
            responseSentMiddleware: $responseSentMiddleware,
            requestStruct: $requestStruct,
            responseStruct: $responseStruct,
        );
    }
}
