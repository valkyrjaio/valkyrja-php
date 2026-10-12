<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Routing\Data;

use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Throwable\Exception\QueueRoutingInvalidRouteNameException;

use function array_merge;

class Route implements RouteContract
{
    /** @var callable(ContainerContract, RouteContract): JobResult */
    protected $handler;

    /**
     * @param non-empty-string                                     $name                      The job name
     * @param non-empty-string                                     $description               The description
     * @param callable(ContainerContract, RouteContract):JobResult $handler                   The handler
     * @param class-string<RouteMatchedMiddlewareContract>[]       $routeMatchedMiddleware    The route matched middleware
     * @param class-string<RouteDispatchedMiddlewareContract>[]    $routeDispatchedMiddleware The route dispatched middleware
     * @param class-string<ThrowableCaughtMiddlewareContract>[]    $throwableCaughtMiddleware The throwable caught middleware
     * @param class-string<SettlingResultMiddlewareContract>[]     $settlingResultMiddleware  The settling result middleware
     * @param class-string<ResultSettledMiddlewareContract>[]      $resultSettledMiddleware   The result settled middleware
     */
    public function __construct(
        protected string $name,
        protected string $description,
        callable $handler,
        protected array $routeMatchedMiddleware = [],
        protected array $routeDispatchedMiddleware = [],
        protected array $throwableCaughtMiddleware = [],
        protected array $settlingResultMiddleware = [],
        protected array $resultSettledMiddleware = [],
    ) {
        $this->validateName($name);

        $this->handler = $handler;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withName(string $name): static
    {
        $this->validateName($name);

        $new = clone $this;

        $new->name = $name;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withDescription(string $description): static
    {
        $new = clone $this;

        $new->description = $description;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHandler(): callable
    {
        return $this->handler;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withHandler(callable $handler): static
    {
        $new = clone $this;

        $new->handler = $handler;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRouteMatchedMiddleware(): array
    {
        return $this->routeMatchedMiddleware;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withRouteMatchedMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->routeMatchedMiddleware = $middleware;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedRouteMatchedMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->routeMatchedMiddleware = array_merge($this->routeMatchedMiddleware, $middleware);

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRouteDispatchedMiddleware(): array
    {
        return $this->routeDispatchedMiddleware;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withRouteDispatchedMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->routeDispatchedMiddleware = $middleware;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedRouteDispatchedMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->routeDispatchedMiddleware = array_merge($this->routeDispatchedMiddleware, $middleware);

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getThrowableCaughtMiddleware(): array
    {
        return $this->throwableCaughtMiddleware;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withThrowableCaughtMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->throwableCaughtMiddleware = $middleware;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedThrowableCaughtMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->throwableCaughtMiddleware = array_merge($this->throwableCaughtMiddleware, $middleware);

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getSettlingResultMiddleware(): array
    {
        return $this->settlingResultMiddleware;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withSettlingResultMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->settlingResultMiddleware = $middleware;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedSettlingResultMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->settlingResultMiddleware = array_merge($this->settlingResultMiddleware, $middleware);

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getResultSettledMiddleware(): array
    {
        return $this->resultSettledMiddleware;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withResultSettledMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->resultSettledMiddleware = $middleware;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedResultSettledMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->resultSettledMiddleware = array_merge($this->resultSettledMiddleware, $middleware);

        return $new;
    }

    /**
     * Validate the job name.
     *
     * @psalm-assert non-empty-string $name
     *
     * @phpstan-assert non-empty-string $name
     */
    protected function validateName(string $name): void
    {
        if ($name === '') {
            throw new QueueRoutingInvalidRouteNameException('Route name must not be empty');
        }
    }
}
