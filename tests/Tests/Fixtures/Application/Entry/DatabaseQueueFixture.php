<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Entry;

use Override;
use Valkyrja\Application\Entry\Database\DatabaseQueue;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\Queue\Client\Manager\DatabaseClient;

/**
 * A database queue entry that reads through an injected manager.
 */
final class DatabaseQueueFixture extends DatabaseQueue
{
    private static ManagerContract|null $injected = null;

    /**
     * Point the entry at a manager, as though connect() had resolved it.
     *
     * @param non-empty-string $queue                The queue jobs are consumed from
     * @param non-empty-string $table                The table jobs are stored in
     * @param int<1, max>      $reservationTimeoutMs The age at which a claim is abandoned
     */
    public static function inject(
        ManagerContract $manager,
        string $queue = 'default',
        string $table = DatabaseClient::DEFAULT_TABLE,
        int $reservationTimeoutMs = self::DEFAULT_RESERVATION_TIMEOUT_MS,
    ): void {
        self::$injected               = $manager;
        self::$manager                = $manager;
        self::$queue                  = $queue;
        self::$table                  = $table;
        self::$reservationTimeoutMs   = $reservationTimeoutMs;
    }

    /**
     * Drop the manager and the overrides, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$injected               = null;
        self::$manager                = null;
        self::$current                = null;
        self::$queue                  = 'default';
        self::$table                  = DatabaseClient::DEFAULT_TABLE;
        self::$reservationTimeoutMs   = self::DEFAULT_RESERVATION_TIMEOUT_MS;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected static function getManager(ContainerContract $container): ManagerContract
    {
        return self::$injected ?? parent::getManager($container);
    }
}
