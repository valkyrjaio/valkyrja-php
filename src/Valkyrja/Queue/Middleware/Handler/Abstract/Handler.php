<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Middleware\Handler\Abstract;

use Override;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Middleware\Contract\JobReceivedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteNotMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\HandlerContract;

use function array_merge;

/**
 * @template Middleware of JobReceivedMiddlewareContract|RouteMatchedMiddlewareContract|RouteNotMatchedMiddlewareContract|RouteDispatchedMiddlewareContract|ThrowableCaughtMiddlewareContract|SettlingResultMiddlewareContract|ResultSettledMiddlewareContract
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
     * Middleware is appended, never deduplicated: scheduling the same class
     * twice runs it twice, which is the developer's bug to fix rather than the
     * framework's to silently paper over.
     *
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
}
