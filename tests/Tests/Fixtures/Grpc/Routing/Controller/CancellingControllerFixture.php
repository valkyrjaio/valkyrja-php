<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Routing\Controller;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;
use Valkyrja\Grpc\Throwable\Exception\CancelledException;

/**
 * A gRPC service controller that cancels, so a test can drive cancellation through the scan-derived
 * handler rather than around it.
 */
#[Service(service: 'pkg.Cancelling')]
final class CancellingControllerFixture
{
    #[Method(name: 'Cancel')]
    public static function cancel(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        throw new CancelledException(
            message: 'the client went away',
            reason: CancellationReason::CLIENT_CANCELLED
        );
    }

    #[Method(name: 'EmitThenFail', clientStreaming: true, serverStreaming: true)]
    public static function emitThenFail(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        $container->getSingleton(ServiceCallContract::class)->send('partial');

        throw new CancelledException(
            message: 'the handler stopped after emitting',
            reason: CancellationReason::CLIENT_CANCELLED
        );
    }

    #[Method(name: 'Expire')]
    public static function expire(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        throw new CancelledException(
            message: 'the deadline elapsed',
            reason: CancellationReason::DEADLINE_EXCEEDED
        );
    }
}
