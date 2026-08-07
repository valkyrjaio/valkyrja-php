<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Data\Contract;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;

interface RouteContract
{
    /**
     * Get the fully-qualified method, `/package.Service/Method` — the map key.
     *
     * @return non-empty-string
     */
    public function getMethod(): string;

    /**
     * Get the service name, `package.Service`.
     *
     * @return non-empty-string
     */
    public function getService(): string;

    /**
     * Get the bare method name, `Method`.
     *
     * @return non-empty-string
     */
    public function getMethodName(): string;

    /**
     * Get the generated protobuf request message type, or null if unspecified.
     *
     * @return class-string|null
     */
    public function getRequestType(): string|null;

    /**
     * Create a new route with the specified request type.
     *
     * @param class-string|null $requestType The request type
     */
    public function withRequestType(string|null $requestType): static;

    /**
     * Get the generated protobuf response message type, or null if unspecified.
     *
     * @return class-string|null
     */
    public function getResponseType(): string|null;

    /**
     * Create a new route with the specified response type.
     *
     * @param class-string|null $responseType The response type
     */
    public function withResponseType(string|null $responseType): static;

    /**
     * Determine whether the client streams multiple request messages.
     */
    public function isClientStreaming(): bool;

    /**
     * Create a new route with the specified client-streaming flag.
     */
    public function withClientStreaming(bool $clientStreaming): static;

    /**
     * Determine whether the server streams multiple response messages.
     */
    public function isServerStreaming(): bool;

    /**
     * Create a new route with the specified server-streaming flag.
     */
    public function withServerStreaming(bool $serverStreaming): static;

    /**
     * Get the route matched middleware.
     *
     * @return class-string<RouteMatchedMiddlewareContract>[]
     */
    public function getRouteMatchedMiddleware(): array;

    /**
     * Create a new route with the specified route matched middleware.
     *
     * @param class-string<RouteMatchedMiddlewareContract> ...$middleware The middleware
     */
    public function withRouteMatchedMiddleware(string ...$middleware): static;

    /**
     * Create a new route with added route matched middleware.
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
     * Create a new route with the specified route dispatched middleware.
     *
     * @param class-string<RouteDispatchedMiddlewareContract> ...$middleware The middleware
     */
    public function withRouteDispatchedMiddleware(string ...$middleware): static;

    /**
     * Create a new route with added route dispatched middleware.
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
     * Create a new route with the specified throwable caught middleware.
     *
     * @param class-string<ThrowableCaughtMiddlewareContract> ...$middleware The middleware
     */
    public function withThrowableCaughtMiddleware(string ...$middleware): static;

    /**
     * Create a new route with added throwable caught middleware.
     *
     * @param class-string<ThrowableCaughtMiddlewareContract> ...$middleware The middleware
     */
    public function withAddedThrowableCaughtMiddleware(string ...$middleware): static;

    /**
     * Get the sending response middleware.
     *
     * @return class-string<SendingResponseMiddlewareContract>[]
     */
    public function getSendingResponseMiddleware(): array;

    /**
     * Create a new route with the specified sending response middleware.
     *
     * @param class-string<SendingResponseMiddlewareContract> ...$middleware The middleware
     */
    public function withSendingResponseMiddleware(string ...$middleware): static;

    /**
     * Create a new route with added sending response middleware.
     *
     * @param class-string<SendingResponseMiddlewareContract> ...$middleware The middleware
     */
    public function withAddedSendingResponseMiddleware(string ...$middleware): static;

    /**
     * Get the response sent middleware.
     *
     * @return class-string<ResponseSentMiddlewareContract>[]
     */
    public function getResponseSentMiddleware(): array;

    /**
     * Create a new route with the specified response sent middleware.
     *
     * @param class-string<ResponseSentMiddlewareContract> ...$middleware The middleware
     */
    public function withResponseSentMiddleware(string ...$middleware): static;

    /**
     * Create a new route with added response sent middleware.
     *
     * @param class-string<ResponseSentMiddlewareContract> ...$middleware The middleware
     */
    public function withAddedResponseSentMiddleware(string ...$middleware): static;

    /**
     * Get the handler that produces a response for a matched call.
     *
     * @return callable(ContainerContract, self): ServiceResponseContract
     */
    public function getHandler(): callable;

    /**
     * Create a new route with the specified handler.
     *
     * @param callable(ContainerContract, self): ServiceResponseContract $handler The handler
     */
    public function withHandler(callable $handler): static;
}
