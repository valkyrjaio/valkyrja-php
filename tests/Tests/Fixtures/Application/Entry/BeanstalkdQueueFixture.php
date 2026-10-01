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
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use Valkyrja\Application\Entry\Beanstalkd\BeanstalkdQueue;
use Valkyrja\Queue\Client\Data\Contract\QueueBeanstalkdClientConfigContract;

/**
 * A beanstalkd queue entry that reserves from an injected connection.
 */
final class BeanstalkdQueueFixture extends BeanstalkdQueue
{
    private static PheanstalkSubscriberInterface|null $injected = null;

    /**
     * Point the entry at a connection, as though connect() had opened it.
     *
     * @param non-empty-string $tube    The tube jobs are consumed from
     * @param int<0, max>      $timeout The seconds a reserve blocks
     */
    public static function inject(
        PheanstalkSubscriberInterface $pheanstalk,
        string $tube = 'default',
        int $timeout = 0,
    ): void {
        self::$injected     = $pheanstalk;
        static::$pheanstalk = $pheanstalk;
        static::$tube       = $tube;
        static::$timeout    = $timeout;
    }

    /**
     * Drop the connection and the overrides, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$injected     = null;
        static::$pheanstalk = null;
        static::$current    = null;
        static::$tube       = 'default';
        static::$timeout    = 1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected static function getPheanstalk(QueueBeanstalkdClientConfigContract $config): PheanstalkSubscriberInterface
    {
        return self::$injected ?? parent::getPheanstalk($config);
    }
}
