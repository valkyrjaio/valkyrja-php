<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Attribute;

use Attribute;
use Valkyrja\Attribute\Contract\ReflectionAwareAttributeContract;
use Valkyrja\Attribute\Trait\ReflectionAwareAttribute;
use Valkyrja\Cli\Interaction\Message\Contract\MessageContract;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Middleware\Contract\ProcessExitingMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Cli\Routing\Data\Contract\ArgumentParameterContract;
use Valkyrja\Cli\Routing\Data\Contract\OptionParameterContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Data\Route as Model;
use Valkyrja\Cli\Routing\Support\NoRouteHandler;
use Valkyrja\Container\Manager\Contract\ContainerContract;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route extends Model implements ReflectionAwareAttributeContract
{
    use ReflectionAwareAttribute;

    /**
     * @param non-empty-string                                                 $name                      The name
     * @param non-empty-string                                                 $description               The description
     * @param (callable(ContainerContract, RouteContract):OutputContract)|null $handler                   The handler
     * @param (callable():MessageContract)|null                                $helpText                  The help text
     * @param class-string<RouteMatchedMiddlewareContract>[]                   $routeMatchedMiddleware    The command matched middleware
     * @param class-string<RouteDispatchedMiddlewareContract>[]                $routeDispatchedMiddleware The command dispatched middleware
     * @param class-string<ThrowableCaughtMiddlewareContract>[]                $throwableCaughtMiddleware The throwable caught middleware
     * @param class-string<ProcessExitingMiddlewareContract>[]                 $processExitingMiddleware  The process exiting middleware
     * @param ArgumentParameterContract[]                                      $arguments                 The arguments
     * @param OptionParameterContract[]                                        $options                   The options
     */
    public function __construct(
        protected string $name,
        protected string $description,
        callable|null $handler = null,
        callable|null $helpText = null,
        protected array $routeMatchedMiddleware = [],
        protected array $routeDispatchedMiddleware = [],
        protected array $throwableCaughtMiddleware = [],
        protected array $processExitingMiddleware = [],
        protected array $arguments = [],
        protected array $options = [],
    ) {
        parent::__construct(
            name: $name,
            description: $description,
            handler: $handler ?? new NoRouteHandler(),
            helpText: $helpText,
            routeMatchedMiddleware: $routeMatchedMiddleware,
            routeDispatchedMiddleware: $routeDispatchedMiddleware,
            throwableCaughtMiddleware: $throwableCaughtMiddleware,
            processExitingMiddleware: $processExitingMiddleware,
            arguments: $arguments,
            options: $options,
        );
    }
}
