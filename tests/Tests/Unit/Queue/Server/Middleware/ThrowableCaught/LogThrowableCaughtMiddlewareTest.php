<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Server\Middleware\ThrowableCaught;

use RuntimeException;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Queue\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class LogThrowableCaughtMiddlewareTest extends TestCase
{
    public function testLogsTheThrowableWithTheJobIdentityAndPassesTheResultThrough(): void
    {
        $throwable = new RuntimeException('boom');
        $job       = new Job(name: 'SendWelcomeEmail', id: 'job-id', attempts: 2, maxAttempts: 5);

        $logger = $this->createMock(LoggerContract::class);
        $logger->expects($this->once())
            ->method('throwable')
            ->with(
                $throwable,
                "Queue Job Error\nJob: SendWelcomeEmail\nId: job-id\nAttempt: 2/5"
            );

        $middleware = new LogThrowableCaughtMiddleware($logger);

        $result = $middleware->throwableCaught(
            $job,
            JobResult::RETRY,
            $throwable,
            new ThrowableCaughtHandler(new Container())
        );

        self::assertSame(JobResult::RETRY, $result);
    }
}
