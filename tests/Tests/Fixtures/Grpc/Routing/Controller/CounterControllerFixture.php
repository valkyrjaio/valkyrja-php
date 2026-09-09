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
 * A server-streaming controller whose response is an unbounded generator, counting how many
 * messages it actually produced.
 *
 * A consumer that stops reading must stop the generator: outbound flow control is the adapter's to
 * apply, and it can only do so because the drain is pull-based the whole way down.
 */
#[Service(service: 'pkg.Counter')]
final class CounterControllerFixture
{
    public static int $produced = 0;

    public static function reset(): void
    {
        self::$produced = 0;
    }

    #[Method(name: 'Count', serverStreaming: true)]
    public static function count(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        $call = $container->getSingleton(ServiceCallContract::class);

        $messages = (static function (): iterable {
            while (true) {
                self::$produced++;

                yield 'message ' . self::$produced;
            }
        })();

        return ServiceResponse::ok()->withMessages($call->cancellable($messages));
    }
}
