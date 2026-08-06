<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Data;

use Valkyrja\Application\Data\Contract\GrpcConfigContract;
use Valkyrja\Application\Data\GrpcConfig;
use Valkyrja\Application\Provider\GrpcApplicationComponentProvider;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class GrpcConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(GrpcConfigContract::class, new GrpcConfig());
    }

    public function testDefaults(): void
    {
        $config = new GrpcConfig();

        self::assertSame('App', $config->namespace);
        self::assertSame('production', $config->environment);
        self::assertFalse($config->debugMode);
        self::assertSame('UTC', $config->timezone);
        self::assertSame('App/Provider/Data', $config->dataPath);
        self::assertSame('App\\Provider\\Data', $config->dataNamespace);
        self::assertSame(50051, $config->port);
        self::assertSame(GrpcConfigContract::DEFAULT_MAX_INBOUND_MESSAGES, $config->maxInboundMessages);
        self::assertCount(1, $config->providers);
        self::assertInstanceOf(GrpcApplicationComponentProvider::class, $config->providers[0]);
        self::assertSame([], $config->callbacks);
        self::assertSame([], $config->callReceivedMiddleware);
        self::assertSame([], $config->routeMatchedMiddleware);
        self::assertSame([], $config->routeNotMatchedMiddleware);
        self::assertSame([], $config->routeDispatchedMiddleware);
        self::assertSame([], $config->throwableCaughtMiddleware);
        self::assertSame([], $config->sendingResponseMiddleware);
        self::assertSame([], $config->responseSentMiddleware);
    }

    public function testTheDefaultInboundMessageCap(): void
    {
        self::assertSame(1000, GrpcConfigContract::DEFAULT_MAX_INBOUND_MESSAGES);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new GrpcConfig(
            namespace: 'My',
            debugMode: true,
            environment: 'testing',
            port: 60000,
            maxInboundMessages: 25,
        );

        self::assertSame('My', $config->namespace);
        self::assertTrue($config->debugMode);
        self::assertSame('testing', $config->environment);
        self::assertSame(60000, $config->port);
        self::assertSame(25, $config->maxInboundMessages);
    }
}
