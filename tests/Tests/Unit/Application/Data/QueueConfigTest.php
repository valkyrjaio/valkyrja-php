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

use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Provider\QueueApplicationComponentProvider;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware;
use Valkyrja\Queue\Server\Middleware\ThrowableCaught\RetryPolicyThrowableCaughtMiddleware;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class QueueConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new QueueConfig();

        self::assertSame('App', $config->namespace);
        self::assertSame(ApplicationInfo::VERSION, $config->version);
        self::assertSame('production', $config->environment);
        self::assertFalse($config->debugMode);
        self::assertSame('UTC', $config->timezone);
        self::assertSame('valkyrja', $config->applicationName);
        self::assertSame(Job::DEFAULT_MAX_ATTEMPTS, $config->defaultMaxAttempts);
        self::assertSame(Job::DEFAULT_RETRY_DELAY_MS, $config->defaultRetryDelayMs);
        self::assertFalse($config->defaultRetryDelayMultiplyByAttempt);
        self::assertInstanceOf(QueueApplicationComponentProvider::class, $config->providers[0]);
        self::assertEmpty($config->callbacks);
    }

    public function testMiddlewareDefaults(): void
    {
        $config = new QueueConfig();

        self::assertEmpty($config->jobReceivedMiddleware);
        self::assertEmpty($config->routeMatchedMiddleware);
        self::assertEmpty($config->routeNotMatchedMiddleware);
        self::assertEmpty($config->routeDispatchedMiddleware);
        self::assertEmpty($config->settlingResultMiddleware);
        self::assertEmpty($config->resultSettledMiddleware);

        // The failure is logged before the policy decides the outcome, so the
        // log middleware sees the seeded result rather than the final one
        self::assertSame(
            [LogThrowableCaughtMiddleware::class, RetryPolicyThrowableCaughtMiddleware::class],
            $config->throwableCaughtMiddleware
        );
    }

    public function testConstructor(): void
    {
        $config = new QueueConfig(
            namespace: 'Other',
            dir: '/tmp',
            version: '1.0.0',
            environment: 'local',
            debugMode: true,
            timezone: 'America/New_York',
            key: 'key',
            dataPath: 'Data',
            dataNamespace: 'Data',
            applicationName: 'worker',
            defaultMaxAttempts: 9,
            defaultRetryDelayMs: 250,
            defaultRetryDelayMultiplyByAttempt: true,
            providers: [],
            callbacks: [],
        );

        self::assertSame('Other', $config->namespace);
        self::assertSame('/tmp', $config->dir);
        self::assertSame('1.0.0', $config->version);
        self::assertSame('local', $config->environment);
        self::assertTrue($config->debugMode);
        self::assertSame('America/New_York', $config->timezone);
        self::assertSame('key', $config->key);
        self::assertSame('Data', $config->dataPath);
        self::assertSame('Data', $config->dataNamespace);
        self::assertSame('worker', $config->applicationName);
        self::assertSame(9, $config->defaultMaxAttempts);
        self::assertSame(250, $config->defaultRetryDelayMs);
        self::assertTrue($config->defaultRetryDelayMultiplyByAttempt);
        self::assertEmpty($config->providers);
    }
}
