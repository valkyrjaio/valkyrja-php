<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Attribute;

use Attribute;
use Valkyrja\Attribute\Contract\ReflectionAwareAttributeContract;
use Valkyrja\Attribute\Trait\ReflectionAwareAttribute;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\Route as Model;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route extends Model implements ReflectionAwareAttributeContract
{
    use ReflectionAwareAttribute;

    /**
     * @param non-empty-string                                            $name                      The job name
     * @param non-empty-string                                            $description               The description
     * @param (callable(ContainerContract, RouteContract):JobResult)|null $handler                   The handler
     * @param class-string<RouteMatchedMiddlewareContract>[]              $routeMatchedMiddleware    The route matched middleware
     * @param class-string<RouteDispatchedMiddlewareContract>[]           $routeDispatchedMiddleware The route dispatched middleware
     * @param class-string<ThrowableCaughtMiddlewareContract>[]           $throwableCaughtMiddleware The throwable caught middleware
     * @param class-string<SettlingResultMiddlewareContract>[]            $settlingResultMiddleware  The settling result middleware
     * @param class-string<ResultSettledMiddlewareContract>[]             $resultSettledMiddleware   The result settled middleware
     */
    public function __construct(
        protected string $name,
        protected string $description,
        callable|null $handler = null,
        protected array $routeMatchedMiddleware = [],
        protected array $routeDispatchedMiddleware = [],
        protected array $throwableCaughtMiddleware = [],
        protected array $settlingResultMiddleware = [],
        protected array $resultSettledMiddleware = [],
    ) {
        parent::__construct(
            name: $name,
            description: $description,
            handler: $handler ?? static fn (ContainerContract $container, RouteContract $route): JobResult => JobResult::ACK,
            routeMatchedMiddleware: $routeMatchedMiddleware,
            routeDispatchedMiddleware: $routeDispatchedMiddleware,
            throwableCaughtMiddleware: $throwableCaughtMiddleware,
            settlingResultMiddleware: $settlingResultMiddleware,
            resultSettledMiddleware: $resultSettledMiddleware,
        );
    }
}
