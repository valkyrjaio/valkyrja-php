<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Routing\Handler;

use RuntimeException;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

/**
 * Handlers whose outcome a test dictates.
 */
final class JobOutcomeFixture
{
    /**
     * The client a pushing handler pushes through.
     *
     * A handler reaches its own application's client through the container, so
     * a test that needs the pushing client to be the one under test sets it
     * here instead.
     */
    public static ClientContract|null $client = null;

    /** @var non-empty-string The job a pushing handler pushes */
    public static string $pushes = 'AlwaysFail';

    /**
     * Drop the client and the pushed job name.
     */
    public static function reset(): void
    {
        self::$client = null;
        self::$pushes = 'AlwaysFail';
    }

    /**
     * A handler that pushes a second job and then gives up itself.
     */
    public static function pushesThenFails(ContainerContract $container, RouteContract $route): JobResult
    {
        self::$client?->push(new Job(name: self::$pushes));

        return JobResult::FAIL;
    }

    /**
     * A handler that always throws, to drive the throwable-caught path.
     */
    public static function throws(ContainerContract $container, RouteContract $route): JobResult
    {
        throw new RuntimeException('the job blew up');
    }
}
