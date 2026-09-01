<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Middleware\Handler\Abstract;

use Override;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\CallReceivedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\HandlerContract;
use Valkyrja\Grpc\Support\Cancellation;

use function array_merge;

/**
 * @template Middleware of CallReceivedMiddlewareContract|RouteMatchedMiddlewareContract|RouteNotMatchedMiddlewareContract|RouteDispatchedMiddlewareContract|ThrowableCaughtMiddlewareContract|SendingResponseMiddlewareContract|ResponseSentMiddlewareContract
 *
 * @implements HandlerContract<Middleware>
 */
abstract class Handler implements HandlerContract
{
    /** @var array<array-key, class-string<Middleware>> */
    protected array $middleware = [];
    /** @var class-string<Middleware>|null */
    protected string|null $next = null;
    /** @var int */
    protected int $index = 0;

    /**
     * @param class-string<Middleware> ...$middleware The middleware
     */
    public function __construct(
        protected ContainerContract $container = new Container(),
        string ...$middleware,
    ) {
        $this->middleware = $middleware;

        $this->updateNext();
    }

    /**
     * @param class-string<Middleware> ...$middleware The middleware to add
     */
    #[Override]
    public function add(string ...$middleware): void
    {
        $this->middleware = array_merge($this->middleware, $middleware);

        $this->updateNext();
    }

    /**
     * Get the next middleware in order to continue handling.
     *
     * @param class-string<Middleware> $middleware The middleware to handle
     *
     * @return Middleware
     */
    protected function getMiddleware(string $middleware): object
    {
        /** @var Middleware $item */
        $item = $this->container->get($middleware);

        $this->index++;

        $this->updateNext();

        return $item;
    }

    /**
     * Update the next middleware to use.
     */
    protected function updateNext(): void
    {
        $this->next = $this->middleware[$this->index] ?? null;
    }

    /**
     * Run the two-question cancellation check for a request-processing stage.
     *
     * @param ServiceCallContract          $call     The current call
     * @param ServiceResponseContract|null $response The response in hand, or null if none exists yet
     *
     * @return ServiceResponseContract|null A cancellation response to fast-exit with, or null to
     *                                      continue normally
     */
    protected function checkCancellation(ServiceCallContract $call, ServiceResponseContract|null $response = null): ServiceResponseContract|null
    {
        return Cancellation::checkAndFinalize($call, $response);
    }
}
