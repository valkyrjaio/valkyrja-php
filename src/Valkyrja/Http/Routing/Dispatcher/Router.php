<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Dispatcher;

use Override;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Message\Enum\StatusCode;
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Http\Message\Response\Factory\Contract\ResponseFactoryContract;
use Valkyrja\Http\Message\Response\Factory\ResponseFactory;
use Valkyrja\Http\Message\Throwable\Exception\HttpResponseException;
use Valkyrja\Http\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Http\Middleware\Handler\ResponseSentHandler;
use Valkyrja\Http\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Http\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Http\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Http\Middleware\Handler\SendingResponseHandler;
use Valkyrja\Http\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Http\Routing\Collection\RouteCollection;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Http\Routing\Matcher\Contract\MatcherContract;
use Valkyrja\Http\Routing\Matcher\Matcher;

use function rawurldecode;

class Router implements RouterContract
{
    protected MatcherContract $matcher;

    public function __construct(
        protected ContainerContract $container = new Container(),
        MatcherContract|null $matcher = null,
        protected ResponseFactoryContract $responseFactory = new ResponseFactory(),
        protected ThrowableCaughtHandlerContract $throwableCaughtHandler = new ThrowableCaughtHandler(),
        protected RouteMatchedHandlerContract $routeMatchedHandler = new RouteMatchedHandler(),
        protected RouteNotMatchedHandlerContract $routeNotMatchedHandler = new RouteNotMatchedHandler(),
        protected RouteDispatchedHandlerContract $routeDispatchedHandler = new RouteDispatchedHandler(),
        protected SendingResponseHandlerContract $sendingResponseHandler = new SendingResponseHandler(),
        protected ResponseSentHandlerContract $responseSentHandler = new ResponseSentHandler()
    ) {
        $this->matcher = $matcher ?? new Matcher(
            collection: new RouteCollection(),
            container: $container
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dispatch(ServerRequestContract $request): ResponseContract
    {
        // Attempt to match the route
        $matchedRoute = $this->attemptToMatchRoute($request);

        // If the route was not matched a response returned
        if ($matchedRoute instanceof ResponseContract) {
            // Dispatch RouteNotMatchedMiddleware
            return $this->routeNotMatchedHandler->routeNotMatched(
                request: $request,
                response: $matchedRoute
            );
        }

        return $this->dispatchRoute(
            request: $request,
            route: $matchedRoute
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dispatchRoute(ServerRequestContract $request, RouteContract $route): ResponseContract
    {
        // The route has been matched
        $this->routeMatched($route);

        // Dispatch the RouteMatchedMiddleware
        $routeAfterMiddleware = $this->routeMatchedHandler->routeMatched(
            request: $request,
            route: $route
        );

        // If the return value after middleware is a response return it
        if ($routeAfterMiddleware instanceof ResponseContract) {
            return $routeAfterMiddleware;
        }

        // Set the route after middleware has potentially modified it in the service container
        $this->container->setSingleton(RouteContract::class, $routeAfterMiddleware);

        $handler = $routeAfterMiddleware->getHandler();

        // Attempt to dispatch the route using any one of the callable options
        $response = $handler($this->container, $routeAfterMiddleware);

        return $this->routeDispatchedHandler->routeDispatched(
            request: $request,
            response: $response,
            route: $routeAfterMiddleware
        );
    }

    /**
     * Match a route, or a response if no route exists, from a given server request.
     *
     * @param ServerRequestContract $request The request
     *
     * @throws HttpResponseException
     */
    protected function attemptToMatchRoute(ServerRequestContract $request): RouteContract|ResponseContract
    {
        // Decode the request uri
        /** @var non-empty-string $requestPath */
        $requestPath = rawurldecode($request->getUri()->getPath());
        // Try to match the route
        $route = $this->matcher->match(
            path: $requestPath,
            requestMethod: $request->getMethod()
        );

        // Return the route if it was found
        if ($route !== null) {
            return $route;
        }

        // If the route matches for any method
        if ($this->matcher->match($requestPath, RequestMethod::ANY) !== null) {
            // Then the route exists but not for the requested method, and so it is not allowed
            return $this->responseFactory->createResponse(
                statusCode: StatusCode::METHOD_NOT_ALLOWED,
            );
        }

        // Otherwise return a response with a 404
        return $this->responseFactory->createResponse(
            statusCode: StatusCode::NOT_FOUND,
        );
    }

    /**
     * Do various stuff after the route has been matched.
     *
     * @param RouteContract $route The route
     */
    protected function routeMatched(RouteContract $route): void
    {
        $this->routeMatchedHandler->add(...$route->getRouteMatchedMiddleware());
        $this->routeDispatchedHandler->add(...$route->getRouteDispatchedMiddleware());
        $this->throwableCaughtHandler->add(...$route->getThrowableCaughtMiddleware());
        $this->sendingResponseHandler->add(...$route->getSendingResponseMiddleware());
        $this->responseSentHandler->add(...$route->getResponseSentMiddleware());

        // Set the found route in the service container
        $this->container->setSingleton(RouteContract::class, $route);
    }
}
