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

use Valkyrja\Application\Data\Contract\HttpConfigContract;
use Valkyrja\Application\Data\HttpConfig;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class HttpConfigTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(HttpConfigContract::class, new HttpConfig());
    }

    public function testDefaults(): void
    {
        $config = new HttpConfig();

        self::assertSame('App', $config->namespace);
        self::assertSame('production', $config->environment);
        self::assertFalse($config->debugMode);
        self::assertSame('UTC', $config->timezone);
        self::assertSame('App/Provider/Data', $config->dataPath);
        self::assertSame('App\\Provider\\Data', $config->dataNamespace);
        self::assertSame('valkyrja', $config->applicationName);
        self::assertCount(1, $config->providers);
        self::assertCount(1, $config->routeNotMatchedMiddleware);
        self::assertCount(2, $config->throwableCaughtMiddleware);
        self::assertSame([], $config->callbacks);
        self::assertSame([], $config->requestReceivedMiddleware);
        self::assertSame([], $config->routeMatchedMiddleware);
        self::assertSame([], $config->routeDispatchedMiddleware);
        self::assertSame([], $config->sendingResponseMiddleware);
        self::assertSame([], $config->responseSentMiddleware);
    }

    public function testCustomValuesAreStored(): void
    {
        $config = new HttpConfig(
            namespace: 'My',
            debugMode: true,
            environment: 'testing',
            applicationName: 'my-app',
        );

        self::assertSame('My', $config->namespace);
        self::assertTrue($config->debugMode);
        self::assertSame('testing', $config->environment);
        self::assertSame('my-app', $config->applicationName);
    }
}
