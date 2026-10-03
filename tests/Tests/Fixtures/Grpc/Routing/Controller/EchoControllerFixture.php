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
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

/**
 * A bidirectional gRPC service controller: it reads its live inbound stream and pushes each reply
 * through the call's sink while still reading, then returns a terminal response carrying only the
 * status and trailing metadata.
 */
#[Service(service: 'pkg.Echo')]
final class EchoControllerFixture
{
    #[Method(name: 'Echo', clientStreaming: true, serverStreaming: true)]
    public static function echo(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        $call = $container->getSingleton(ServiceCallContract::class);

        foreach ($call->getMessages() as $message) {
            $call->send($message);
        }

        return ServiceResponse::ok();
    }
}
