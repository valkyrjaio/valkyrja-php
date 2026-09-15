<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Data\Contract;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;

interface RouteContract
{
    /**
     * Get the job name this route is keyed by.
     *
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Get a new instance with the specified name.
     *
     * @param non-empty-string $name The name
     */
    public function withName(string $name): static;

    /**
     * Get the description.
     *
     * @return non-empty-string
     */
    public function getDescription(): string;

    /**
     * Get a new instance with the specified description.
     *
     * @param non-empty-string $description The description
     */
    public function withDescription(string $description): static;

    /**
     * Get the handler.
     *
     * @return callable(ContainerContract, RouteContract): JobResult
     */
    public function getHandler(): callable;

    /**
     * Get a new instance with the specified handler.
     *
     * @param callable(ContainerContract, RouteContract): JobResult $handler The handler
     */
    public function withHandler(callable $handler): static;

    /**
     * Get the route matched middleware.
     *
     * @return class-string<RouteMatchedMiddlewareContract>[]
     */
    public function getRouteMatchedMiddleware(): array;

    /**
     * Get a new instance with the specified route matched middleware.
     *
     * @param class-string<RouteMatchedMiddlewareContract> ...$middleware The middleware
     */
    public function withRouteMatchedMiddleware(string ...$middleware): static;

    /**
     * Get a new instance with added route matched middleware.
     *
     * @param class-string<RouteMatchedMiddlewareContract> ...$middleware The middleware
     */
    public function withAddedRouteMatchedMiddleware(string ...$middleware): static;

    /**
     * Get the route dispatched middleware.
     *
     * @return class-string<RouteDispatchedMiddlewareContract>[]
     */
    public function getRouteDispatchedMiddleware(): array;

    /**
     * Get a new instance with the specified route dispatched middleware.
     *
     * @param class-string<RouteDispatchedMiddlewareContract> ...$middleware The middleware
     */
    public function withRouteDispatchedMiddleware(string ...$middleware): static;

    /**
     * Get a new instance with added route dispatched middleware.
     *
     * @param class-string<RouteDispatchedMiddlewareContract> ...$middleware The middleware
     */
    public function withAddedRouteDispatchedMiddleware(string ...$middleware): static;

    /**
     * Get the throwable caught middleware.
     *
     * @return class-string<ThrowableCaughtMiddlewareContract>[]
     */
    public function getThrowableCaughtMiddleware(): array;

    /**
     * Get a new instance with the specified throwable caught middleware.
     *
     * @param class-string<ThrowableCaughtMiddlewareContract> ...$middleware The middleware
     */
    public function withThrowableCaughtMiddleware(string ...$middleware): static;

    /**
     * Get a new instance with added throwable caught middleware.
     *
     * @param class-string<ThrowableCaughtMiddlewareContract> ...$middleware The middleware
     */
    public function withAddedThrowableCaughtMiddleware(string ...$middleware): static;

    /**
     * Get the settling result middleware.
     *
     * @return class-string<SettlingResultMiddlewareContract>[]
     */
    public function getSettlingResultMiddleware(): array;

    /**
     * Get a new instance with the specified settling result middleware.
     *
     * @param class-string<SettlingResultMiddlewareContract> ...$middleware The middleware
     */
    public function withSettlingResultMiddleware(string ...$middleware): static;

    /**
     * Get a new instance with added settling result middleware.
     *
     * @param class-string<SettlingResultMiddlewareContract> ...$middleware The middleware
     */
    public function withAddedSettlingResultMiddleware(string ...$middleware): static;

    /**
     * Get the result settled middleware.
     *
     * @return class-string<ResultSettledMiddlewareContract>[]
     */
    public function getResultSettledMiddleware(): array;

    /**
     * Get a new instance with the specified result settled middleware.
     *
     * @param class-string<ResultSettledMiddlewareContract> ...$middleware The middleware
     */
    public function withResultSettledMiddleware(string ...$middleware): static;

    /**
     * Get a new instance with added result settled middleware.
     *
     * @param class-string<ResultSettledMiddlewareContract> ...$middleware The middleware
     */
    public function withAddedResultSettledMiddleware(string ...$middleware): static;
}
