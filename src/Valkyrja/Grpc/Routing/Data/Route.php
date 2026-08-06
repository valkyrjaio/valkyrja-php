<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Data;

use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingInvalidMethodException;

use function array_merge;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * Immutable route implementation.
 */
class Route implements RouteContract
{
    /** @var callable(ContainerContract, RouteContract): ServiceResponseContract */
    protected $handler;

    /** @var non-empty-string */
    protected string $service;

    /** @var non-empty-string */
    protected string $methodName;

    /**
     * @param non-empty-string                                                   $method                    The fully-qualified method
     * @param callable(ContainerContract, RouteContract):ServiceResponseContract $handler                   The handler
     * @param class-string|null                                                  $requestType               The request type
     * @param class-string|null                                                  $responseType              The response type
     * @param class-string<RouteMatchedMiddlewareContract>[]                     $routeMatchedMiddleware    The route matched middleware
     * @param class-string<RouteDispatchedMiddlewareContract>[]                  $routeDispatchedMiddleware The route dispatched middleware
     * @param class-string<ThrowableCaughtMiddlewareContract>[]                  $throwableCaughtMiddleware The throwable caught middleware
     * @param class-string<SendingResponseMiddlewareContract>[]                  $sendingResponseMiddleware The sending response middleware
     * @param class-string<ResponseSentMiddlewareContract>[]                     $responseSentMiddleware    The response sent middleware
     */
    public function __construct(
        protected string $method,
        callable $handler,
        protected string|null $requestType = null,
        protected string|null $responseType = null,
        protected bool $clientStreaming = false,
        protected bool $serverStreaming = false,
        protected array $routeMatchedMiddleware = [],
        protected array $routeDispatchedMiddleware = [],
        protected array $throwableCaughtMiddleware = [],
        protected array $sendingResponseMiddleware = [],
        protected array $responseSentMiddleware = [],
    ) {
        $this->handler    = $handler;
        $this->service    = self::serviceOf($method);
        $this->methodName = self::methodNameOf($method);
    }

    /**
     * Extract the `package.Service` portion of a `/package.Service/Method` method.
     *
     * @param string $method The fully-qualified method
     *
     * @throws GrpcRoutingInvalidMethodException
     *
     * @return non-empty-string
     */
    protected static function serviceOf(string $method): string
    {
        $firstSlash = strpos($method, '/');
        $lastSlash  = strrpos($method, '/');

        if ($firstSlash !== 0 || $lastSlash === false || $lastSlash <= $firstSlash + 1) {
            throw new GrpcRoutingInvalidMethodException("Invalid gRPC method `$method`; expected `/package.Service/Method`");
        }

        /** @var non-empty-string $service */
        $service = substr($method, $firstSlash + 1, $lastSlash - $firstSlash - 1);

        return $service;
    }

    /**
     * Extract the `Method` portion of a `/package.Service/Method` method.
     *
     * @param string $method The fully-qualified method
     *
     * @throws GrpcRoutingInvalidMethodException
     *
     * @return non-empty-string
     */
    protected static function methodNameOf(string $method): string
    {
        $lastSlash = strrpos($method, '/');

        if ($lastSlash === false || $lastSlash === strlen($method) - 1) {
            throw new GrpcRoutingInvalidMethodException("Invalid gRPC method `$method`; expected `/package.Service/Method`");
        }

        /** @var non-empty-string $methodName */
        $methodName = substr($method, $lastSlash + 1);

        return $methodName;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRequestType(): string|null
    {
        return $this->requestType;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withRequestType(string|null $requestType): static
    {
        $new = clone $this;

        $new->requestType = $requestType;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getResponseType(): string|null
    {
        return $this->responseType;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withResponseType(string|null $responseType): static
    {
        $new = clone $this;

        $new->responseType = $responseType;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isClientStreaming(): bool
    {
        return $this->clientStreaming;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withClientStreaming(bool $clientStreaming): static
    {
        $new = clone $this;

        $new->clientStreaming = $clientStreaming;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isServerStreaming(): bool
    {
        return $this->serverStreaming;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withServerStreaming(bool $serverStreaming): static
    {
        $new = clone $this;

        $new->serverStreaming = $serverStreaming;

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
    public function getSendingResponseMiddleware(): array
    {
        return $this->sendingResponseMiddleware;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withSendingResponseMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->sendingResponseMiddleware = $middleware;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedSendingResponseMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->sendingResponseMiddleware = array_merge($this->sendingResponseMiddleware, $middleware);

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getResponseSentMiddleware(): array
    {
        return $this->responseSentMiddleware;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withResponseSentMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->responseSentMiddleware = $middleware;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedResponseSentMiddleware(string ...$middleware): static
    {
        $new = clone $this;

        $new->responseSentMiddleware = array_merge($this->responseSentMiddleware, $middleware);

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
}
