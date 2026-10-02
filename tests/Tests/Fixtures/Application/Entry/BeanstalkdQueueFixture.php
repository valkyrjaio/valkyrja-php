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
use Pheanstalk\Contract\PheanstalkManagerInterface;
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use Valkyrja\Application\Entry\Beanstalkd\BeanstalkdQueue;
use Valkyrja\Queue\Client\Data\Contract\QueueBeanstalkdClientConfigContract;

/**
 * A beanstalkd queue entry that reserves from an injected connection.
 */
final class BeanstalkdQueueFixture extends BeanstalkdQueue
{
    private static (PheanstalkManagerInterface&PheanstalkSubscriberInterface)|null $injected = null;

    /**
     * Point the entry at a connection, as though connect() had opened it.
     *
     * @param non-empty-string $tube    The tube jobs are consumed from
     * @param int<0, max>      $timeout The seconds a reserve blocks
     */
    public static function inject(
        PheanstalkManagerInterface&PheanstalkSubscriberInterface $pheanstalk,
        string $tube = 'default',
        int $timeout = 0,
    ): void {
        self::$injected     = $pheanstalk;
        self::$pheanstalk   = $pheanstalk;
        self::$tube         = $tube;
        self::$timeout      = $timeout;
    }

    /**
     * Drop the connection and the overrides, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$injected     = null;
        self::$pheanstalk   = null;
        self::$current      = null;
        self::$tube         = 'default';
        self::$timeout      = 1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected static function getPheanstalk(QueueBeanstalkdClientConfigContract $config): PheanstalkManagerInterface&PheanstalkSubscriberInterface
    {
        return self::$injected ?? parent::getPheanstalk($config);
    }
}
