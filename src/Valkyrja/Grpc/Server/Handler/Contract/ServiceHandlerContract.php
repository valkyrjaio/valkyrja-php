<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Server\Handler\Contract;

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;

interface ServiceHandlerContract
{
    /**
     * Run CallReceived then the Router, converting any thrown throwable via ThrowableCaught.
     *
     * Includes the entry-point cancellation pre-check — the one location where no response exists
     * yet.
     *
     * @param ServiceCallContract $call The inbound call
     */
    public function handle(ServiceCallContract $call): ServiceResponseContract;

    /**
     * Run the SendingResponse stage over a response.
     *
     * Always runs, including on the error and cancellation paths.
     *
     * @param ServiceCallContract     $call     The inbound call
     * @param ServiceResponseContract $response The response produced by `handle()`
     */
    public function sending(ServiceCallContract $call, ServiceResponseContract $response): ServiceResponseContract;

    /**
     * Run the ResponseSent stage after the response has been written to the wire.
     *
     * @param ServiceCallContract     $call     The inbound call
     * @param ServiceResponseContract $response The response that was written
     */
    public function terminate(ServiceCallContract $call, ServiceResponseContract $response): void;

    /**
     * Run `handle()` then `sending()`.
     *
     * The adapter writes the returned response to the wire, then calls `terminate()`.
     *
     * @param ServiceCallContract $call The inbound call
     */
    public function run(ServiceCallContract $call): ServiceResponseContract;
}
