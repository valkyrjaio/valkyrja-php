<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Attribute;

use Attribute;
use Valkyrja\Attribute\Contract\ReflectionAwareAttributeContract;
use Valkyrja\Attribute\Trait\ReflectionAwareAttribute;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route implements ReflectionAwareAttributeContract
{
    use ReflectionAwareAttribute;

    /** @var (callable(ContainerContract, RouteContract):ServiceResponseContract)|null */
    public $handler;

    /**
     * @param non-empty-string                                                          $name                      The remote method's name, e.g. `SayHello`
     * @param (callable(ContainerContract, RouteContract):ServiceResponseContract)|null $handler                   The handler
     * @param class-string|null                                                         $requestType               The generated protobuf request message type
     * @param class-string|null                                                         $responseType              The generated protobuf response message type
     * @param class-string<RouteMatchedMiddlewareContract>[]                            $routeMatchedMiddleware    The route matched middleware
     * @param class-string<RouteDispatchedMiddlewareContract>[]                         $routeDispatchedMiddleware The route dispatched middleware
     * @param class-string<ThrowableCaughtMiddlewareContract>[]                         $throwableCaughtMiddleware The throwable caught middleware
     * @param class-string<SendingResponseMiddlewareContract>[]                         $sendingResponseMiddleware The sending response middleware
     * @param class-string<ResponseSentMiddlewareContract>[]                            $responseSentMiddleware    The response sent middleware
     */
    public function __construct(
        public string $name,
        callable|null $handler = null,
        public string|null $requestType = null,
        public string|null $responseType = null,
        public bool $clientStreaming = false,
        public bool $serverStreaming = false,
        public array $routeMatchedMiddleware = [],
        public array $routeDispatchedMiddleware = [],
        public array $throwableCaughtMiddleware = [],
        public array $sendingResponseMiddleware = [],
        public array $responseSentMiddleware = [],
    ) {
        $this->handler = $handler;
    }
}
