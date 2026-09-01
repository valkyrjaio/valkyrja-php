<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Server\Handler;

use Override;
use Throwable;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Message\Status\Status;
use Valkyrja\Grpc\Middleware\Handler\CallReceivedHandler;
use Valkyrja\Grpc\Middleware\Handler\Contract\CallReceivedHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Grpc\Middleware\Handler\ResponseSentHandler;
use Valkyrja\Grpc\Middleware\Handler\SendingResponseHandler;
use Valkyrja\Grpc\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Grpc\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Grpc\Routing\Dispatcher\Router;
use Valkyrja\Grpc\Server\Handler\Contract\ServiceHandlerContract;
use Valkyrja\Grpc\Support\Cancellation;
use Valkyrja\Grpc\Throwable\Exception\CancelledException;

class ServiceHandler implements ServiceHandlerContract
{
    public function __construct(
        protected ContainerContract $container = new Container(),
        protected RouterContract $router = new Router(),
        protected CallReceivedHandlerContract $callReceivedHandler = new CallReceivedHandler(),
        protected ThrowableCaughtHandlerContract $throwableCaughtHandler = new ThrowableCaughtHandler(),
        protected SendingResponseHandlerContract $sendingResponseHandler = new SendingResponseHandler(),
        protected ResponseSentHandlerContract $responseSentHandler = new ResponseSentHandler(),
        protected bool $debug = false,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws Throwable
     */
    #[Override]
    public function handle(ServiceCallContract $call): ServiceResponseContract
    {
        try {
            $response = $this->dispatchRouter($call);
        } catch (Throwable $throwable) {
            $response = $this->getResponseFromThrowable($throwable);
            $response = $this->throwableCaughtHandler->throwableCaught($call, $response, $throwable);
        }

        // Set the returned response in the container
        $this->container->setSingleton(ServiceResponseContract::class, $response);

        return $response;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function sending(ServiceCallContract $call, ServiceResponseContract $response): ServiceResponseContract
    {
        $sent = $this->sendingResponseHandler->sendingResponse($call, $response);

        // Set the returned response in the container
        $this->container->setSingleton(ServiceResponseContract::class, $sent);

        return $sent;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function terminate(ServiceCallContract $call, ServiceResponseContract $response): void
    {
        // Dispatch the response sent middleware
        $this->responseSentHandler->responseSent($call, $response);
    }

    /**
     * @inheritDoc
     *
     * @throws Throwable
     */
    #[Override]
    public function run(ServiceCallContract $call): ServiceResponseContract
    {
        return $this->sending($call, $this->handle($call));
    }

    /**
     * Dispatch the call via the router.
     *
     * @param ServiceCallContract $call The call
     */
    protected function dispatchRouter(ServiceCallContract $call): ServiceResponseContract
    {
        // Set the call object in the container
        $this->container->setSingleton(ServiceCallContract::class, $call);

        $cancelled = Cancellation::checkAndFinalize($call);

        if ($cancelled !== null) {
            return $cancelled;
        }

        // Dispatch the call received middleware
        $callAfterMiddleware = $this->callReceivedHandler->callReceived($call);

        // If the return value after middleware is a response return it
        if ($callAfterMiddleware instanceof ServiceResponseContract) {
            return $callAfterMiddleware;
        }

        // Set the returned call in the container
        $this->container->setSingleton(ServiceCallContract::class, $callAfterMiddleware);

        return $this->router->dispatch($callAfterMiddleware);
    }

    /**
     * Get a response from a throwable.
     *
     * The framework maps only cancellation and the catch-all: domain outcomes are returned by the
     * handler on the response, exactly as HTTP handlers return status codes, so there is no
     * domain-exception hierarchy to map here. ThrowableCaught middleware can substitute any
     * response it likes for an application that wants one.
     *
     * @param Throwable $throwable The throwable
     *
     * @throws Throwable
     */
    protected function getResponseFromThrowable(Throwable $throwable): ServiceResponseContract
    {
        if ($this->debug) {
            throw $throwable;
        }

        if ($throwable instanceof CancelledException) {
            return ServiceResponse::cancelled($throwable->getReason());
        }

        return ServiceResponse::of(Status::internal());
    }
}
